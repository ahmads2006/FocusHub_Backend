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
        if (Auth::id() !== $image->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.cannot_share_image')], 403);
        }

        $link = $this->service->generate($image, null, null, 1);
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
            'max_access'        => 'nullable|integer|min:1',
            'auto_rotate'       => 'nullable|boolean',
            'require_watermark' => 'nullable|boolean',
            'label'             => 'nullable|string|max:100',
        ]);

        $modelClass = $request->shareable_type;
        $model      = $modelClass::findOrFail($request->shareable_id);

        if (Auth::id() !== $model->user_id) {
            return response()->json(['success' => false, 'message' => __('messages.cannot_share_item')], 403);
        }

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
        if ($link->password && !$request->attributes->get('shared_link_authenticated')) {
            return response()->json([
                'success'           => false,
                'requires_password' => true,
                'message'           => __('messages.link_password_protected'),
            ], 401);
        }

        $shareable = $link->shareable;

        if ($shareable instanceof Album) {
            $shareable->load(['images' => function($query) {
                $query->withoutGlobalScope(\App\Models\Scopes\ShadowPrivacyScope::class)
                      ->with(['storage', 'settings', 'user']);
            }]);

            return response()->json([
                'success' => true,
                'type'    => 'album',
                'data'    => [
                    'album'      => $shareable,
                    'permission' => $link->permission,
                    'link'       => [
                        'access_count' => $link->access_count,
                        'max_access'   => $link->max_access,
                        'expires_at'   => $link->expires_at,
                    ],
                ],
            ]);
        }

        if ($shareable instanceof Image) {
            $shareable->load(['storage', 'settings', 'user']);
            return response()->json([
                'success' => true,
                'type'    => 'image',
                'data'    => [
                    'image'      => $shareable,
                    'permission' => $link->permission,
                    'link'       => [
                        'access_count' => $link->access_count,
                        'max_access'   => $link->max_access,
                        'expires_at'   => $link->expires_at,
                    ],
                ],
            ]);
        }

        return response()->json(['success' => false, 'message' => __('messages.unsupported_content')], 404);
    }

    /**
     * Verify a password-protected shared link.
     */
    public function verifyPassword(Request $request, string $token): JsonResponse
    {
        $link   = $request->attributes->get('shared_link');
        $authId = $request->attributes->get('shared_link_auth_id');

        if (!$link) {
            return response()->json(['success' => false, 'message' => __('messages.invalid_link_short')], 404);
        }

        $request->validate(['password' => 'required|string']);

        if (Hash::check($request->password, $link->password)) {
            return response()->json([
                'success'    => true,
                'message'    => __('messages.verified_successfully'),
                'auth_token' => encrypt("link_auth_{$authId}"),
            ]);
        }

        return response()->json(['success' => false, 'message' => __('messages.incorrect_password')], 401);
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
                $disk = $image->storage->disk ?? 'public';
                $path = $image->storage->path ?? null;

                if ($path && Storage::disk($disk)->exists($path)) {
                    $stream = Storage::disk($disk)->readStream($path);
                    if ($stream) {
                        $tempFile = tempnam(sys_get_temp_dir(), 'album_img_');
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
}
