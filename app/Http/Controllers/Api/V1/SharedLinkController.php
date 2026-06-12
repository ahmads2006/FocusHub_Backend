<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Models\SharedLink;
use App\Services\Security\SharedLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SharedLinkController extends Controller
{
    protected SharedLinkService $service;

    public function __construct(SharedLinkService $service)
    {
        $this->service = $service;
    }

    /**
     * Generate a "View Once" self-destructing link for an image.
     */
    public function generateShareOnceLink(Image $image): JsonResponse
    {
        $this->authorize('share', $image);

        // Load settings relationship to inherit download and watermark preferences
        $image->loadMissing('settings');
        
        $allowDownload = (bool)($image->settings?->allow_download ?? false);
        $permission = $allowDownload ? 'download' : 'view';
        $watermarkOnDownload = $image->settings?->watermark_on_download ?? null;

        $link = $this->service->generate(
            $image,
            null,
            null,
            1, // max_access = 1 (Self-destruct after one view)
            $permission,
            true,
            $watermarkOnDownload,
            'رابط لمرة واحدة'
        );
        $url  = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => __('messages.self_destruct_link_created'),
            'data'    => ['url' => $url],
        ]);
    }

    /**
     * Generate a custom shared link with flexible options.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'shareable_id'      => 'required|string',
            'shareable_type'    => 'required|string|in:App\Models\Image,App\Models\Album',
            'expires_in'        => 'nullable|integer',
            'password'          => 'nullable|string|min:4',
            'permission'        => 'nullable|in:view,download',
            'max_access'        => 'nullable|integer|min:0', // Allowed 0 for unlimited
            'auto_rotate'       => 'nullable|boolean',
            'require_watermark' => 'nullable|boolean',
            'label'             => 'nullable|string|max:100',
        ]);

        $modelClass = $request->shareable_type;
        $model      = $modelClass::findOrFail($request->shareable_id);

        $this->authorize('share', $model);

        $expiry     = $request->expires_in ? now()->addHours((int) $request->expires_in) : null;
        $permission = $request->permission ?? 'view';
        $maxAccess  = $request->max_access ? (int) $request->max_access : null;
        $password   = $request->password ?: null;
        $autoRotate = $request->input('auto_rotate', false);
        $requireWatermark = $request->has('require_watermark') ? (bool) $request->input('require_watermark') : null;
        $label      = $request->input('label');

        $link = $this->service->generate($model, $expiry, $password, $maxAccess, $permission, $autoRotate, $requireWatermark, $label);
        $url  = $this->service->getFullUrl($link);

        return response()->json([
            'success' => true,
            'message' => __('messages.share_link_created'),
            'data'    => [
                'url'        => $url,
                'expires_at' => $expiry,
                'permission' => $permission,
            ],
        ]);
    }

    /**
     * View shared content via token (JSON API version).
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $link = $request->attributes->get('shared_link');

        if (!$link) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_link')], 404);
        }

        // Check if password is required
        if (!empty($link->password) && !$request->attributes->get('shared_link_authenticated')) {
            return response()->json([
                'success'           => false,
                'requires_password' => true,
                'message'           => __('messages.link_password_protected'),
            ], 401);
        }

        $shareable = $link->shareable;

        if ($shareable instanceof Album) {
            $images = Image::withoutGlobalScopes()
                ->where('album_id', $shareable->id)
                ->with(['storage', 'settings', 'user', 'meta', 'tags', 'moderation'])
                ->withCount(['likes', 'bookmarks'])
                ->get();
            
            $likedImageIds = [];
            $bookmarkedImageIds = [];
            if (Auth::check()) {
                $likedImageIds = \App\Models\Like::where('user_id', Auth::id())
                    ->whereIn('image_id', $images->pluck('id'))
                    ->pluck('image_id')->toArray();

                $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', Auth::id())
                    ->whereIn('image_id', $images->pluck('id'))
                    ->pluck('image_id')->toArray();
            }

            $images->transform(function ($image) use ($likedImageIds, $bookmarkedImageIds) {
                $image->is_liked = in_array($image->id, $likedImageIds);
                $image->is_saved = in_array($image->id, $bookmarkedImageIds);
                $image->likes_count = $image->likes_count ?? 0;
                $image->bookmarks_count = $image->bookmarks_count ?? 0;
                return $image;
            });
            
            return response()->json([
                'success' => true,
                'type'    => 'album',
                'data'    => [
                    'album'      => $shareable,
                    'photos'     => \App\Http\Resources\PhotoResource::collection($images)->resolve(),
                    'permission' => $link->permission,
                    'link'       => [
                        'token'        => $link->token,
                        'access_count' => $link->access_count,
                        'max_access'   => $link->max_access,
                        'expires_at'   => $link->expires_at,
                    ],
                ],
            ]);
        }

        if ($shareable instanceof Image) {
            $shareable = Image::withoutGlobalScopes()
                ->with(['storage', 'settings', 'user', 'meta', 'tags', 'moderation'])
                ->withCount(['likes', 'bookmarks'])
                ->findOrFail($shareable->id);

            if (Auth::check()) {
                $shareable->is_liked = \App\Models\Like::where('user_id', Auth::id())->where('image_id', $shareable->id)->exists();
                $shareable->is_saved = \App\Models\Bookmark::where('user_id', Auth::id())->where('image_id', $shareable->id)->exists();
            } else {
                $shareable->is_liked = false;
                $shareable->is_saved = false;
            }

            return response()->json([
                'success' => true,
                'type'    => 'image',
                'data'    => [
                    'image'      => (new \App\Http\Resources\PhotoResource($shareable))->resolve(),
                    'permission' => $link->permission,
                    'link'       => [
                        'token'        => $link->token,
                        'access_count' => $link->access_count,
                        'max_access'   => $link->max_access,
                        'expires_at'   => $link->expires_at,
                    ],
                ],
            ]);
        }

        \Illuminate\Support\Facades\Log::warning("Shared content unsupported or shareable missing: ", [
            'has_shareable' => (bool)$shareable,
            'type' => $shareable ? get_class($shareable) : null,
            'token' => $token
        ]);
        return response()->json(['success' => false, 'message' => __('messages.unsupported_content')], 404);
    }

    /**
     * Verify a password-protected shared link.
     */
    public function verifyPassword(Request $request, string $token): JsonResponse
    {
        // Manually resolve the link since we moved this route out of the middleware
        $tokenHash = hash('sha256', $token);
        $link = \App\Models\SharedLink::where('token_hash', $tokenHash)
            ->first();

        // If not found by current token hash, try to find by persistent_id
        // (Token may have been rotated by session locking middleware)
        if (!$link) {
            $persistentId = md5($tokenHash);
            $link = \App\Models\SharedLink::where('persistent_id', $persistentId)->first();
        }

        // Also check Redis for ephemeral links
        if (!$link) {
            $cachedData = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$tokenHash}");
            if ($cachedData) {
                $link = new \App\Models\SharedLink($cachedData);
            }
        }

        if (!$link || ($link->expires_at && $link->expires_at->isPast())) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_link_short')], 404);
        }

        $request->validate(['password' => 'required|string']);

        \Illuminate\Support\Facades\Log::info("Attempting password check for link:", [
            'token_hash' => $tokenHash,
            'persistent_id' => $link->persistent_id ?? 'none',
            'has_password_in_model' => !empty($link->password),
            'password_hash_start' => substr($link->password, 0, 10) . '...'
        ]);

        if (Hash::check($request->password, $link->password)) {
            // 🔑 Use persistent_id for the auth key when available (stable across token rotations)
            // Fallback: use the LINK's current token hash (not the URL token hash which may be outdated)
            $currentTokenHash = $link->token_hash ?? $tokenHash;
            $authKeyId = $link->persistent_id ?? $currentTokenHash;
            $authKey = "shared_link_auth_" . $authKeyId;
            
            // 🔓 Store authentication in session (standard)
            $request->session()->put($authKey, true);
            $request->session()->save();

            // 🚀 FINGERPRINT-BASED FALLBACK (Stable across proxy IP changes)
            $ipAuthKey = "shared_link_fp_auth_" . $authKeyId . "_" . md5($request->userAgent());
            \Illuminate\Support\Facades\Cache::put($ipAuthKey, true, now()->addHours(2));

            \Illuminate\Support\Facades\Log::info("Shared link password verified successfully!", [
                'authKey' => $authKey,
                'fpKey' => $ipAuthKey,
                'ip' => $request->ip(),
                'ua_md5' => md5($request->userAgent()),
                'current_token_hash' => $currentTokenHash,
            ]);

            // 🍪 MANUAL COOKIE FALLBACK (Cross-subdomain compatible)
            $cookieName = "sl_auth_" . substr($authKeyId, 0, 8);
            $cookie = cookie(
                $cookieName, 
                '1', 
                240, // 4 hours
                '/', 
                '.opalshot.studio', // 🌐 CRITICAL: Share across all subdomains
                true, // Secure
                true, // HttpOnly
                false, 
                'None'
            );
            
            // 📌 Return the current valid token so the frontend can sync its URL
            $currentToken = $link->token;

            return response()->json([
                'success'    => true,
                'message'    => __('messages.verified_successfully'),
                'auth_key'   => $authKey,
                'token'      => $currentToken, // Frontend needs this to make the next API call
            ])->withCookie($cookie);
        }

        return response()->json(['success' => false, 'message' => __('messages.incorrect_password')], 403);
    }

    /**
     * Download a shared album as ZIP.
     */
    public function downloadAlbum(Request $request, string $token): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $link      = $request->attributes->get('shared_link');
        $shareable = $link->shareable;

        if (!$shareable instanceof Album) {
            return response()->json(['success' => false, 'message' => __('messages.link_not_album')], 404);
        }

        if ($link->permission !== 'download') {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized_download')], 403);
        }

        $items = $shareable->photos ?? $shareable->images ?? collect();
        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => __('messages.album_empty')], 404);
        }

        $zipFileName = 'Album_' . Str::slug($shareable->title) . '_' . time() . '.zip';
        $tempDir     = storage_path('app/temp');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $zipFilePath = $tempDir . '/' . $zipFileName;
        $zip         = new \ZipArchive();

        if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $tempFiles = [];
            foreach ($items as $index => $image) {
                $path = $image->storage->path ?? $image->path ?? null;
                if (!$path) continue;

                $preferredDisk = $image->storage->disk ?? 'public';
                if ($preferredDisk === 'spaces') {
                    $preferredDisk = 's3';
                }

                $activeDisk = null;
                $disksToCheck = array_unique([$preferredDisk, 'public', 'local', 's3']);
                
                foreach ($disksToCheck as $d) {
                    if (Storage::disk($d)->exists($path)) {
                        $activeDisk = $d;
                        break;
                    }
                }

                if ($activeDisk) {
                    $stream = Storage::disk($activeDisk)->readStream($path);
                    if ($stream) {
                        $tempFile = tempnam($tempDir, 'album_img_');
                        $out      = fopen($tempFile, 'wb');
                        stream_copy_to_stream($stream, $out);
                        fclose($out);
                        fclose($stream);
                        $tempFiles[] = $tempFile;

                        $ext       = pathinfo($image->filename ?? $path, PATHINFO_EXTENSION) ?: 'jpg';
                        $baseName  = Str::slug($image->title ?: 'image_' . ($index + 1));
                        $nameInZip = sprintf('%03d', $index + 1) . '_' . $baseName . '.' . $ext;
                        $zip->addFile($tempFile, $nameInZip);
                    }
                }
            }
            $zip->close();

            foreach ($tempFiles as $tempFile) {
                if (file_exists($tempFile)) @unlink($tempFile);
            }

            if (file_exists($zipFilePath)) {
                return response()->download($zipFilePath)->deleteFileAfterSend(true);
            }
        }

        return response()->json(['success' => false, 'message' => __('messages.zip_failed')], 500);
    }

    /**
     * Show a specific image within a shared link.
     */
    public function showImage(Request $request, string $token, Image $image): JsonResponse
    {
        $link = $request->attributes->get('shared_link');

        if (!$link) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_link')], 404);
        }

        // Validate the image belongs to the shared link context
        $isValid = false;
        if ($link->shareable_type === Image::class) {
            $isValid = ($link->shareable_id === $image->id);
        } elseif ($link->shareable_type === Album::class) {
            $isValid = ($link->shareable_id === $image->album_id);
        }

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        $image->load(['meta', 'user', 'tags', 'settings', 'storage', 'moderation'])->loadCount(['likes', 'bookmarks']);

        // Fetch other images in the same album as related photos
        $related = collect();
        if ($image->album_id) {
            $related = Image::withoutGlobalScopes()
                ->where('album_id', $image->album_id)
                ->where('id', '!=', $image->id)
                ->with(['tags', 'settings', 'storage', 'moderation', 'user'])
                ->latest()
                ->take(12)
                ->get();
        }

        // Fallback or fill with public tag-matching images
        if ($related->count() < 12) {
            $tagNames = $image->tags->pluck('name')->toArray();
            $moreQuery = Image::where('id', '!=', $image->id)
                ->where('privacy', 'public')
                ->with(['tags', 'settings', 'storage', 'moderation', 'user'])
                ->withCount('analytics');

            if (!empty($tagNames)) {
                $moreQuery->withAnyTags($tagNames);
            }

            $more = $moreQuery->take(12 - $related->count())->get();
            $related = $related->concat($more);
        }

        // Hydrate is_liked / is_saved states for related and main image to prevent N+1 queries
        $likedImageIds = [];
        $bookmarkedImageIds = [];
        if (Auth::check()) {
            $imageIdsToCheck = $related->pluck('id')->concat([$image->id])->toArray();
            
            $likedImageIds = \App\Models\Like::where('user_id', Auth::id())
                ->whereIn('image_id', $imageIdsToCheck)
                ->pluck('image_id')->toArray();

            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', Auth::id())
                ->whereIn('image_id', $imageIdsToCheck)
                ->pluck('image_id')->toArray();
        }

        $image->is_liked = in_array($image->id, $likedImageIds);
        $image->is_saved = in_array($image->id, $bookmarkedImageIds);

        $related->transform(function ($img) use ($likedImageIds, $bookmarkedImageIds) {
            $img->is_liked = in_array($img->id, $likedImageIds);
            $img->is_saved = in_array($img->id, $bookmarkedImageIds);
            return $img;
        });

        // Ensure URLs are set
        $related->each(function($img) {
            $img->append(['url', 'original_url']);
        });

        return response()->json([
            'image' => (new \App\Http\Resources\PhotoResource($image))->resolve(),
            'related' => \App\Http\Resources\PhotoResource::collection($related)->resolve(),
            'has_related' => $related->isNotEmpty(),
            'match_base' => count($image->tags)
        ]);
    }

    /**
     * Download a specific image within a shared link.
     */
    public function downloadImage(Request $request, string $token, Image $image): JsonResponse
    {
        $link = $request->attributes->get('shared_link');

        if (!$link) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_link')], 404);
        }

        // Validate the image belongs to the shared link context
        $isValid = false;
        if ($link->shareable_type === Image::class) {
            $isValid = ($link->shareable_id === $image->id);
        } elseif ($link->shareable_type === Album::class) {
            $isValid = ($link->shareable_id === $image->album_id);
        }

        if (!$isValid) {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized')], 403);
        }

        // Check permission
        if ($link->permission !== 'download') {
            return response()->json(['success' => false, 'message' => __('messages.unauthorized_download')], 403);
        }

        // Setup session variables so ImagePolicy and AssetAccessController can authorize the guest download properly
        session()->put("shared_link_access_{$image->id}", $link->permission);
        session()->put("shared_link_watermark_{$image->id}", $link->require_watermark);
        session()->put("shared_link_id_{$image->id}", $link->id);
        if ($link->shareable_type === Album::class) {
            session()->put("shared_link_access_album_{$link->shareable_id}", $link->permission);
        }

        // Instantiate DownloadController and call download / downloadOriginal
        $downloadController = app(\App\Http\Controllers\Web\DownloadController::class);
        $useOriginal = $request->query('original') === 'true';

        if ($useOriginal) {
            return $downloadController->downloadOriginal($image);
        } else {
            return $downloadController->download($image);
        }
    }
}
