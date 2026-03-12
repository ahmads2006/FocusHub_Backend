<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Image;
use App\Models\ProtectedImage;
use App\Services\Core\AssetDeliveryService;
use App\Services\Security\WatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        // 1. Verify the signature
        if (!$request->hasValidSignature()) {
            abort(403, 'Unauthorized access or expired link.');
        }

        // 2. Authorization check
        if (!$this->deliveryService->canAccessOriginal($image)) {
            abort(403, 'You do not have permission to download the original file.');
        }

        // 3. Determine whether watermark should be applied
        $isOwner              = auth()->id() === $image->user_id;
        $sessionLinkWatermark = session("shared_link_watermark_{$image->id}");
        $sessionLinkAccess    = session("shared_link_access_{$image->id}");

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

        // 4a. No watermark needed → serve original
        if (!$shouldWatermark) {
            return $this->streamImageFile($image);
        }

        // 4b. Watermark needed → look for a pre-rendered ProtectedImage first
        $protected = \App\Models\ProtectedImage::where('image_id', $image->id)
            ->whereNull('reverted_at')
            ->latest()
            ->first();

        if ($protected && Storage::disk('public')->exists($protected->path)) {
            $filename = pathinfo($image->filename, PATHINFO_FILENAME) . '_secured.jpg';
            return $this->streamFile($protected->path, $filename);
        }

        // 4c. No pre-rendered copy → generate on-the-fly via SecureShield
        try {
            $settings = [
                'watermark_text'    => $image->user->name,
                'mode'              => 'signature',
                'smart_positioning' => true,
                'dynamic_blending'  => true,
                'digital_archiving' => true,
            ];

            $protectedUrl = $this->secureShield->protect($image, $settings);

            // Correctly strip the URL base to get the storage-relative path
            $relativePath = ltrim(str_replace(Storage::disk('public')->url(''), '', $protectedUrl), '/');
            $filename     = pathinfo($image->filename, PATHINFO_FILENAME) . '_secured.jpg';

            return $this->streamFile($relativePath, $filename);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SecureShield download failed: " . $e->getMessage());
            abort(500, 'Security processing failed. Please try again.');
        }
    }

    /**
     * Helper to stream a file from the public disk.
     */
    protected function streamImageFile(Image $image): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        // Smart disk resolution - same as SecureShieldService
        $publicPath = Storage::disk('public')->path($image->path);
        $localPath  = Storage::disk('local')->path($image->path);

        if (file_exists($publicPath)) {
            return $this->streamFile($image->path, $image->filename);
        }

        if (file_exists($localPath)) {
            // Stream directly from private local disk
            $mime = Storage::disk('local')->mimeType($image->path);
            $size = Storage::disk('local')->size($image->path);
            return response()->stream(function () use ($image) {
                $stream = Storage::disk('local')->readStream($image->path);
                if ($stream) { fpassthru($stream); fclose($stream); }
            }, 200, [
                'Content-Type'        => $mime,
                'Content-Length'      => $size,
                'Content-Disposition' => 'attachment; filename="' . $image->filename . '"',
                'Cache-Control'       => 'no-cache, private',
            ]);
        }

        abort(404, 'Image file not found.');
    }

    /**
     * Helper to stream a file with path masking and security headers.
     */
    protected function streamFile(string $path, string $filename)
    {
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File not found in secure vault.');
        }

        $size = Storage::disk('public')->size($path);
        $mime = Storage::disk('public')->mimeType($path);

        return response()->stream(function () use ($path) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache, private',
        ]);
    }
}
