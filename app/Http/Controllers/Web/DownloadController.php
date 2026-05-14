<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\Core\ImageKitService;
use App\Services\Core\AssetDeliveryService;
use Illuminate\Support\Facades\Auth;

class DownloadController extends Controller
{
    protected $imageKit;
    protected $deliveryService;

    public function __construct(ImageKitService $imageKit, AssetDeliveryService $deliveryService)
    {
        $this->imageKit = $imageKit;
        $this->deliveryService = $deliveryService;
    }

    /**
     * Download watermarked version via ImageKit
     */
    public function download(Image $image)
    {
        // Permission Check: If allow_download is false, only owner can download
        if (!($image->settings?->allow_download ?? true)) {
            if (Auth::id() !== $image->user_id) {
                abort(403, 'تنزيل هذه الصورة غير مسموح به من قبل المالك.');
            }
        }

        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
        
        if (!$path) {
            abort(404, 'الصورة غير موجودة.');
        }

        $image->load(['user', 'settings']);
        $watermarkText = $image->user->name ?? 'OpalShot';

        // Read watermark customization from settings
        $fontSize = (int) ($image->settings?->watermark_font_size ?? 80);
        $opacity  = (int) ($image->settings?->watermark_opacity ?? 70);
        $color    = $image->settings?->watermark_color ?? 'FFFFFF';

        // Apply opacity to color as hex alpha (e.g. FFFFFF + B3 for 70%)
        $alphaHex = str_pad(dechex(round($opacity / 100 * 255)), 2, '0', STR_PAD_LEFT);
        $colorWithAlpha = $color . $alphaHex;

        // Scale font size for ImageKit (ImageKit uses pixels, user sets a relative value)
        $ikFontSize = max(20, $fontSize);

        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
        if ($path) {
            $path = ltrim($path, '/');
            if (str_starts_with(strtolower($path), 'opticvault/')) {
                $path = substr($path, strlen('opticvault/'));
            }
        }

        // Generate signed watermarked URL via ImageKit with customization
        $url = $this->imageKit->getWatermarkedUrl($path, $watermarkText, true, 30, $ikFontSize, $colorWithAlpha);

        \Illuminate\Support\Facades\Log::info("Generated Watermark URL for image {$image->id}: fontSize={$ikFontSize}, opacity={$opacity}%, color=#{$colorWithAlpha}");

        return response()->json(['url' => $url]);
    }

    /**
     * Download original (no watermark)
     */
    public function downloadOriginal(Image $image)
    {
        \Illuminate\Support\Facades\Log::info("downloadOriginal() called for image {$image->id}");

        // Only accessible if user has permission
        if (!$this->deliveryService->canAccessOriginal($image)) {
            abort(403, 'غير مصرح لك بتنزيل النسخة الأصلية من هذه الصورة.');
        }
        
        // Ensure shared link session doesn't interfere with this direct gallery download
        session()->forget("shared_link_access_{$image->id}");
        session()->forget("shared_link_watermark_{$image->id}");

        // 🛡️ WATERMARK ENFORCEMENT: If the owner enabled watermark_on_download
        // and the requesting user is NOT the owner, force watermarked download.
        $image->load('settings');
        $hasWatermark = (bool) ($image->settings?->watermark_on_download ?? false);
        $isOwner = \Illuminate\Support\Facades\Auth::id() === $image->user_id;

        if ($hasWatermark && !$isOwner) {
            \Illuminate\Support\Facades\Log::info("Watermark enforced for non-owner on image {$image->id}");
            return $this->download($image);
        }

        $url = $this->deliveryService->getUrl($image, 'original');

        return response()->json(['url' => $url]);
    }
}
