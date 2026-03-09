<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Services\AssetDeliveryService;
use App\Services\WatermarkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class AssetAccessController extends Controller
{
    protected $deliveryService;
    protected $watermarkService;

    public function __construct(AssetDeliveryService $deliveryService, WatermarkService $watermarkService)
    {
        $this->deliveryService = $deliveryService;
        $this->watermarkService = $watermarkService;
    }

    /**
     * Serve the original high-resolution file through a secured route.
     * Applies dynamic watermark if requested by a non-owner and photographer preference is enabled.
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

        // 3. Process the file
        if (!Storage::disk('public')->exists($image->path)) {
            abort(404);
        }

        $isOwner = auth()->id() === $image->user_id;
        $shouldWatermark = $image->user->dynamic_watermark && !$isOwner;

        if ($shouldWatermark) {
            $manager = new ImageManager(new Driver());
            $img = $manager->read(Storage::disk('public')->get($image->path));
            
            // Apply Watermark
            $this->watermarkService->apply($image, $img);

            // Stream watermarked version directly (memory only)
            return response($img->toWebp(quality: 90)->toString())
                ->header('Content-Type', 'image/webp')
                ->header('Content-Disposition', 'attachment; filename="' . pathinfo($image->filename, PATHINFO_FILENAME) . '_watermarked.webp"');
        }

        return Storage::disk('public')->response($image->path, $image->filename);
    }
}
