<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\ProtectedImage;
use App\Services\AssetDeliveryService;
use App\Services\WatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetAccessController extends Controller
{
    protected $deliveryService;
    protected $secureShield;

    public function __construct(AssetDeliveryService $deliveryService, \App\Services\SecureShieldService $secureShield)
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

        // 2. Perform deep authorization check
        if (!$this->deliveryService->canAccessOriginal($image)) {
            abort(403, 'You do not have permission to download the original file.');
        }

        // 3. File existence check
        if (!Storage::disk('public')->exists($image->path)) {
            abort(404);
        }

        // 4. Determine whether watermark should be applied
        $isOwner = auth()->id() === $image->user_id;
        
        // Retrieve permissions from session (persisted by ValidateSharedLink middleware)
        $sessionLinkWatermark = session("shared_link_watermark_{$image->id}");
        $sessionLinkAccess = session("shared_link_access_{$image->id}");

        if ($isOwner) {
            // Owner always receives the clean original
            $shouldWatermark = false;
        } elseif ($sessionLinkWatermark !== null) {
            // Shared-link session override takes priority
            $shouldWatermark = (bool) $sessionLinkWatermark;
        } elseif ($sessionLinkAccess === 'view') {
            // Strictly enforce view-only session if download attempted (defense in depth)
            abort(403, 'This secure link is limited to view-only access.');
        } elseif ($image->watermark_on_download) {
            // Image-level specific preference
            $shouldWatermark = true;
        } else {
            // Fall back to the photographer's global watermark preference
            $shouldWatermark = (bool) ($image->user->dynamic_watermark ?? false);
        }

        // 5. Serve
        if (!$shouldWatermark) {
            return $this->streamFile($image->path, $image->filename);
        }

        // 5a. Use SecureShield to serve (handles caching/optimization internally)
        try {
            // We use the photographer's generic identity for the on-the-fly watermark
            $settings = [
                'watermark_text' => $image->user->name,
                'mode' => 'grid', // Default to grid for maximum protection on non-owner downloads
                'smart_positioning' => true,
                'dynamic_blending' => true,
                'digital_archiving' => true
            ];

            // SecureShield returns a URL to the protected asset (existing or newly generated)
            $protectedUrl = $this->secureShield->protect($image, $settings);
            
            // Extract the path from the URL to serve as a download
            $path = parse_url($protectedUrl, PHP_URL_PATH);
            $path = ltrim($path, '/storage/');
            
            $filename = pathinfo($image->filename, PATHINFO_FILENAME) . '_secured.jpg';
            return $this->streamFile($path, $filename);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SecureShield processing failed: " . $e->getMessage());
            
            // SECURITY: If protection fails, DO NOT serve the original clean image. 
            // Abort with error to prevent identity exposure.
            abort(500, 'Security processing failed. Please try again later.');
        }
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
