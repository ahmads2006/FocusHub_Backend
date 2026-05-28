<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Image;
use App\Models\ProtectedImage;
use App\Services\Core\AssetDeliveryService;
use App\Services\Security\WatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class AssetAccessController extends Controller
{
    protected $deliveryService;
    protected $secureShield;

    public function __construct(AssetDeliveryService $deliveryService, \App\Services\Security\SecureShieldService $secureShield)
    {
        $this->deliveryService = $deliveryService;
        $this->secureShield = $secureShield;
    }

    /**
     * Serve the original high-resolution file through a secured route.
     *
     * Watermark priority:
     *   1. If viewer is the image owner → always serve original (no watermark).
     *   2. If request comes via a SharedLink with require_watermark set explicitly
     *      → honour that link setting (true = watermark, false = no watermark).
     *   3. Otherwise → fall back to the photographer's global `dynamic_watermark` preference.
     *
     * This controller uses SecureShield v3.0 as the exclusive protection engine.
     */
    public function serveOriginal(Request $request, Image $image)
    {
        \Illuminate\Support\Facades\Log::info("AssetAccess: serveOriginal called for image {$image->id}. Signature valid: " . ($request->hasValidSignature() ? 'YES' : 'NO'));
        
        // 1. Verify the signature
        if (!$request->hasValidSignature()) {
            abort(403, 'Unauthorized access or expired link.');
        }

        // 2. Authorization check
        if (!$this->deliveryService->canAccessOriginal($image)) {
            \Illuminate\Support\Facades\Log::info("AssetAccess: Authorization failed for user " . auth()->id());
            abort(403, 'You do not have permission to download the original file.');
        }

        // 2b. Red Layer: Rejected Content (Critical violations)
        if ($image->isRejected()) {
            abort(403, 'This image has been rejected and cannot be downloaded.');
        }

        // 3. Determine whether watermark should be applied
        $isOwner              = Auth::check() && Auth::id() === $image->user_id;
        $sessionLinkWatermark = session("shared_link_watermark_{$image->id}");
        $sessionLinkAccess    = session("shared_link_access_{$image->id}");

        \Illuminate\Support\Facades\Log::info("AssetAccess: isOwner: " . ($isOwner ? 'YES' : 'NO') . " (User ID: " . Auth::id() . ", Image Owner: " . $image->user_id . ")");

        if ($sessionLinkWatermark !== null) {
            // Shared-link session override takes priority (even for owner testing)
            $shouldWatermark = (bool) $sessionLinkWatermark;
        } elseif ($isOwner) {
            // Owner always receives the clean original otherwise
            $shouldWatermark = false;
        } elseif ($sessionLinkAccess === 'view') {
            abort(403, 'This secure link is limited to view-only access.');
        } elseif ($image->watermark_on_download) {
            $shouldWatermark = true;
        } else {
            $shouldWatermark = (bool) ($image->user->dynamic_watermark ?? false);
        }

        \Illuminate\Support\Facades\Log::info("AssetAccess: final shouldWatermark: " . ($shouldWatermark ? 'YES' : 'NO'));

        // 4a. No watermark needed → serve original
        if (!$shouldWatermark) {
            $imagekitPath = $image->imagekit_file_path ?? null;
            if ($imagekitPath) {
                $imageKitService = app(\App\Services\Core\ImageKitService::class);
                $url = $imageKitService->generateSignedUrl($imagekitPath, [['format' => 'webp', 'quality' => 'auto']], 60);
                \Illuminate\Support\Facades\Log::info("AssetAccess: Redirecting to signed ImageKit URL for original image {$image->id}");
                return redirect($url);
            }
            return $this->streamImageFile($image, 'inline');
        }

        // 4b. Watermark via ImageKit CDN overlay (preferred — no file modification)
        $imagekitPath = $image->imagekit_file_path ?? null;
        if ($imagekitPath) {
            $imageKitService = app(\App\Services\Core\ImageKitService::class);
            
            // ─── CASCADE: Per-Image → User Global → User Name → Default ───
            $image->load('settings');
            $userSettings = \App\Models\UserSetting::where('user_id', $image->user_id)->first();
            
            $watermarkType = $image->settings?->watermark_type ?? $userSettings?->watermark_mode ?? 'text';
            
            // ─── LOGO VALIDATION: Fall back to text if logo path is invalid ───
            $watermarkText = null;
            if ($watermarkType === 'logo') {
                $logoPath = $userSettings?->watermark_logo;
                $isValidLogo = !empty($logoPath) 
                    && $logoPath !== 'default_logo.png'
                    && !str_starts_with($logoPath, 'blob:')
                    && !str_starts_with($logoPath, 'http://localhost')
                    && !str_starts_with($logoPath, 'data:');
                
                if ($isValidLogo) {
                    $watermarkText = $logoPath;
                } else {
                    \Illuminate\Support\Facades\Log::error("AssetAccess: Download blocked - invalid logo path '{$logoPath}'.");
                    abort(400, 'لا يمكن تنزيل الصورة مسارها غير صالح. يرجى إعادة رفع اللوغو في الإعدادات.');
                }
            }
            
            if ($watermarkType === 'text') {
                // Text
                $rawText = $image->settings?->watermark_text;
                if (empty($rawText) || trim($rawText) === '') {
                    $rawText = $userSettings?->watermark_text;
                }
                if (empty($rawText) || trim($rawText) === '') {
                    $rawText = $image->user->name ?? 'OpalShot';
                }
                $watermarkText = '© ' . trim(str_replace('©', '', $rawText));
            }
            
            // Font size, opacity, color
            $fontSize = (int) ($image->settings?->watermark_font_size ?? 80);
            $ikFontSize = max(20, $fontSize);
            
            $rawOpacity = $image->settings?->watermark_opacity;
            if ($rawOpacity === null) {
                $userOpacity = $userSettings?->watermark_opacity;
                $opacity = ($userOpacity !== null) ? (int) ($userOpacity * 100) : 70;
            } else {
                $opacity = (int) $rawOpacity;
            }
            
            $rawColor = $image->settings?->watermark_color;
            if (empty($rawColor)) {
                $userColor = $userSettings?->watermark_text_color;
                $color = $userColor ? ltrim($userColor, '#') : 'FFFFFF';
            } else {
                $color = ltrim($rawColor, '#');
            }
            
            $alphaHex = str_pad(dechex(round($opacity / 100 * 255)), 2, '0', STR_PAD_LEFT);
            $colorWithAlpha = $color . $alphaHex;
            
            $watermarkedUrl = $imageKitService->getWatermarkedUrl($imagekitPath, $watermarkText, true, 30, $ikFontSize, $colorWithAlpha, $watermarkType);

            \Illuminate\Support\Facades\Log::info("AssetAccess: Redirecting to ImageKit watermarked URL for image {$image->id} with text='{$watermarkText}'");
            return redirect($watermarkedUrl);
        }

        // 4c. Fallback: check for a pre-rendered ProtectedImage
        $protected = \App\Models\ProtectedImage::where('image_id', $image->id)
            ->whereNull('reverted_at')
            ->latest()
            ->first();

        if ($protected && Storage::disk('public')->exists($protected->path)) {
            $filename = pathinfo($image->filename, PATHINFO_FILENAME) . '_secured.jpg';
            return $this->streamFile($protected->path, $filename);
        }

        // 4d. Last resort: generate on-the-fly via SecureShield (local/S3 images only)
        try {
            // Resolve watermark text using the same cascade as ImageKit path
            if (!isset($watermarkText)) {
                $image->load('settings');
                $userSettings = $userSettings ?? \App\Models\UserSetting::where('user_id', $image->user_id)->first();
                $rawText = $image->settings?->watermark_text;
                if (empty($rawText) || trim($rawText) === '') {
                    $rawText = $userSettings?->watermark_text;
                }
                if (empty($rawText) || trim($rawText) === '') {
                    $rawText = $image->user->name ?? 'OpalShot';
                }
                $watermarkText = '© ' . trim(str_replace('©', '', $rawText));
            }

            $settings = [
                'watermark_text'    => $watermarkText,
                'mode'              => 'signature',
                'smart_positioning' => true,
                'dynamic_blending'  => true,
                'digital_archiving' => true,
            ];

            $protectedUrl = $this->secureShield->protect($image, $settings);

            $relativePath = ltrim(str_replace(Storage::disk('public')->url(''), '', $protectedUrl), '/');
            $filename     = pathinfo($image->filename, PATHINFO_FILENAME) . '_secured.jpg';

            return $this->streamFile($relativePath, $filename);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SecureShield download failed: " . $e->getMessage());
            abort(500, 'Security processing failed. Please try again.');
        }
    }

    /**
     * Serve a preview image inline (for use in <img> src tags).
     * Uses Content-Disposition: inline so the browser renders it directly.
     */
    public function servePreview(Request $request, Image $image)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Unauthorized access or expired link.');
        }

        // 🛡️ ANTI-THEFT: Block direct access when user tries to open the link in a new tab or browser address bar
        if ($request->header('Sec-Fetch-Dest') === 'document') {
            abort(403, 'Direct image access is forbidden.');
        }

        // 1. Authorization check
        $isOwner = Auth::check() && Auth::id() === $image->user_id;

        if (!$isOwner && $image->privacy !== 'public') {
            // SHARED MEDIA EXCEPTION (v18.0): Allow access if image was shared in chat with current user
            $isSharedInChat = \App\Models\Message::where('image_id', $image->id)
                ->where(function ($q) {
                    $q->where('sender_id', Auth::id())
                      ->orWhere('receiver_id', Auth::id());
                })->exists();

            $hasAlbumAccess = false;
            if ($image->album) {
                $album = $image->album;
                if ($album->privacy === 'public') {
                    $hasAlbumAccess = true;
                } elseif (session()->has("shared_link_access_album_{$album->id}")) {
                    $hasAlbumAccess = true;
                } elseif (Auth::check() && (Auth::id() === $album->user_id || $album->collaborators()->where('user_id', Auth::id())->exists())) {
                    $hasAlbumAccess = true;
                }
            }

            if (!$isSharedInChat && !$hasAlbumAccess) {
                abort(403, 'You do not have permission to view this image.');
            }
        }

        // 2. Red Layer: Rejected Content (Critical violations)
        if ($image->isRejected()) {
            abort(403, 'This image has been rejected due to content policy violations.');
        }

        $imagekitPath = $image->imagekit_file_path ?? null;

        // Generate Signed preview URL for safe/owner views if it's in the cloud
        if ($imagekitPath) {
            $imageKitService = app(\App\Services\Core\ImageKitService::class);
            
            // Read context-based dimensions
            $context = $request->input('context', 'gallery');
            $width = null;
            $height = null;

            switch ($context) {
                case 'avatar':
                case 'icon':
                    $width = 150;
                    $height = 150;
                    break;
                case 'thumbnail':
                case 'square':
                    $width = 400;
                    $height = 400;
                    break;
                case 'card':
                    $width = 400;
                    $height = 300;
                    break;
                case 'list':
                    $width = 200;
                    $height = 150;
                    break;
                case 'placeholder':
                    $width = 20;
                    $height = 20;
                    break;
                case 'gallery':
                case 'preview':
                default:
                    $width = 800;
                    break;
            }

            $transformations = [['format' => 'webp', 'quality' => 'auto']];
            if ($width) {
                $transformations[0]['width'] = (string)$width;
            }
            if ($height) {
                $transformations[0]['height'] = (string)$height;
                $transformations[0]['crop'] = 'at_max';
            }

            // Expiry is set to 1 minute — enough for browser redirect to load image, short enough to prevent sharing
            $url = $imageKitService->generateSignedUrl($imagekitPath, $transformations, 1);
            return redirect($url);
        }

        return $this->streamImageFile($image, 'inline');
    }

    /**
     * Helper to stream a file from the appropriate storage disk.
     */
    protected function streamImageFile(Image $image, string $disposition = 'attachment'): \Symfony\Component\HttpFoundation\Response
    {
        \Illuminate\Support\Facades\Log::info("AssetAccess: streamImageFile called for ID {$image->id}. Path: {$image->path}, Disposition: {$disposition}");

        // 1. Try public disk (Bulk uploads / Public previews)
        if (Storage::disk('public')->exists($image->path)) {
            \Illuminate\Support\Facades\Log::info("AssetAccess: Found on public disk.");
            return $this->streamFromDisk('public', $image->path, $image->filename, $disposition);
        }

        // 2. Try local disk (Private/Quarantined bulk uploads)
        if (Storage::disk('local')->exists($image->path)) {
            \Illuminate\Support\Facades\Log::info("AssetAccess: Found on local disk.");
            return $this->streamFromDisk('local', $image->path, $image->filename, $disposition);
        }

        // 3. Try s3 disk (Cloud individual uploads)
        try {
            // 🚀 PERFORMANCE: Release session early
            if (session_id()) session_write_close();

            $disk = Storage::disk('s3');
            
            // Check if file exists before attempting to stream (optional but safer for debugging)
            if (!$disk->exists($image->path)) {
                 \Illuminate\Support\Facades\Log::error("AssetAccess: File NOT found on S3: {$image->path}");
                 abort(404, 'Image source not found.');
            }

            $stream = $disk->readStream($image->path);
            $mime = $disk->mimeType($image->path) ?? 'image/jpeg';
            $size = $disk->size($image->path);

            return response()->stream(
                function () use ($stream) {
                    fpassthru($stream);
                    if (is_resource($stream)) fclose($stream);
                },
                200,
                [
                    'Content-Type' => $mime,
                    'Content-Length' => $size,
                    'Content-Disposition' => ($disposition === 'inline' ? 'inline' : 'attachment') . '; filename="' . $image->filename . '"',
                    'Cache-Control' => 'public, max-age=31536000',
                ]
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("AssetAccess: Streaming failed for image {$image->id}: " . $e->getMessage());
            abort(500, 'Error streaming image.');
        }

        \Illuminate\Support\Facades\Log::error("AssetAccess: File not found on any disk for image {$image->id} at path: {$image->path}");
        abort(404, 'Image file not found in any vault storage.');
    }

    protected function streamFromDisk(string $disk, string $path, string $filename, string $disposition = 'attachment'): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $storage = Storage::disk($disk);
        $size = $storage->size($path);
        $mime = $storage->mimeType($path);
        
        \Illuminate\Support\Facades\Log::info("AssetAccess: Streaming from {$disk}. Path: {$path}, Mime: {$mime}, Size: {$size}, Disposition: {$disposition}");

        // For inline display (gallery/preview), use inline disposition so browsers render the image
        $contentDisposition = $disposition === 'inline'
            ? 'inline; filename="' . $filename . '"'
            : 'attachment; filename="' . $filename . '"';

        $cacheControl = $disposition === 'inline' 
            ? 'public, max-age=2592000, immutable' // 30 days for gallery previews (cache-busted by ?v=)
            : 'private, max-age=3600';             // 1 hour for secure downloads

        return response()->stream(function () use ($storage, $path, $disk) {
            $stream = $storage->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            } else {
                \Illuminate\Support\Facades\Log::error("AssetAccess: readStream failed for disk {$disk} at path: {$path}");
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Content-Disposition' => $contentDisposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $cacheControl,
        ]);
    }

    /**
     * Legacy helper kept for backward compatibility if needed within the class.
     */
    protected function streamFile(string $path, string $filename)
    {
        return $this->streamFromDisk('public', $path, $filename);
    }
}
