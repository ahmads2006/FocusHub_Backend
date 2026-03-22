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
                $url = $imageKitService->generateSignedUrl($imagekitPath, [], 60);
                \Illuminate\Support\Facades\Log::info("AssetAccess: Redirecting to signed ImageKit URL for original image {$image->id}");
                return redirect($url);
            }
            return $this->streamImageFile($image);
        }

        // 4b. Watermark via ImageKit CDN overlay (preferred — no file modification)
        $imagekitPath = $image->imagekit_file_path ?? null;
        if ($imagekitPath) {
            $imageKitService = app(\App\Services\Core\ImageKitService::class);
            $watermarkText = $image->user->name ?? 'OpticVault';
            $watermarkedUrl = $imageKitService->getWatermarkedUrl($imagekitPath, $watermarkText);

            \Illuminate\Support\Facades\Log::info("AssetAccess: Redirecting to ImageKit watermarked URL for image {$image->id}");
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
            $settings = [
                'watermark_text'    => $image->user->name,
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

        // 1. Authorization check
        $isOwner = Auth::check() && Auth::id() === $image->user_id;
        if (!$isOwner && $image->privacy !== 'public') {
            abort(403, 'You do not have permission to view this image.');
        }

        // 2. Red Layer: Rejected Content (Critical violations)
        if ($image->isRejected()) {
            abort(403, 'This image has been rejected due to content policy violations.');
        }

        // 3. Yellow Layer: Pending Review / Sensitive Content
        $isSensitive = $image->is_sensitive || $image->isPendingReview();
        
        $imagekitPath = $image->imagekit_file_path ?? null;

        // Skip blur for owners so they can review their own content
        if ($isSensitive && !$isOwner) {
            if ($imagekitPath) {
                $imageKitService = app(\App\Services\Core\ImageKitService::class);
                $blurredUrl = $imageKitService->getBlurredUrl($imagekitPath);
                
                \Illuminate\Support\Facades\Log::info("AssetAccess: Serving BLURRED preview (Yellow Layer) for image {$image->id}");
                return redirect($blurredUrl);
            }
        }

        // Generate Signed preview URL for safe/owner views if it's in the cloud
        if ($imagekitPath) {
            $imageKitService = app(\App\Services\Core\ImageKitService::class);
            $url = $imageKitService->generateSignedUrl($imagekitPath, [['format' => 'webp', 'quality' => 'auto']], 30);
            return redirect($url);
        }

        return $this->streamImageFile($image, 'inline');
    }

    /**
     * Helper to stream a file from the appropriate storage disk.
     */
    protected function streamImageFile(Image $image, string $disposition = 'attachment'): \Symfony\Component\HttpFoundation\StreamedResponse
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
            if (Storage::disk('s3')->exists($image->path)) {
                \Illuminate\Support\Facades\Log::info("AssetAccess: Found on s3 disk.");
                return $this->streamFromDisk('s3', $image->path, $image->filename, $disposition);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("AssetAccess: S3 check failed for image {$image->id}: " . $e->getMessage());
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
            'Cache-Control' => 'no-cache, private',
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
