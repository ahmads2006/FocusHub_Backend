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

        // ─── CASCADE PRIORITY: Per-Image Settings → User Global Settings → Defaults ───
        // Load user's global watermark settings as fallback
        $userSettings = \App\Models\UserSetting::where('user_id', $image->user_id)->first();

        \Illuminate\Support\Facades\Log::info("Watermark Debug for image {$image->id}", [
            'image_settings_exists' => $image->settings !== null,
            'image_watermark_text' => $image->settings?->watermark_text,
            'user_watermark_text' => $userSettings?->watermark_text,
            'user_name' => $image->user?->name,
        ]);

        // 1. Watermark Type: per-image → user global → default 'text'
        $watermarkType = $image->settings?->watermark_type 
            ?? $userSettings?->watermark_mode 
            ?? 'text';
        
        if ($watermarkType === 'logo') {
            $watermarkText = $userSettings?->watermark_logo_path ?: 'default_logo.png'; 
        } else {
            // 2. Watermark Text: per-image → user global → user name → 'OpalShot'
            $rawText = $image->settings?->watermark_text;
            if (empty($rawText) || trim($rawText) === '') {
                $rawText = $userSettings?->watermark_text;
            }
            if (empty($rawText) || trim($rawText) === '') {
                $rawText = $image->user?->name ?? 'OpalShot';
            }
            $watermarkText = '© ' . trim(str_replace('©', '', $rawText));
        }

        // 3. Font Size: per-image → default 80
        $fontSize = (int) ($image->settings?->watermark_font_size ?? 80);

        // 4. Opacity: per-image → user global → default 70
        $rawOpacity = $image->settings?->watermark_opacity;
        if ($rawOpacity === null || $rawOpacity === '') {
            // User global opacity is stored as 0-1 float, convert to 0-100 percent
            $userOpacity = $userSettings?->watermark_opacity;
            $opacity = ($userOpacity !== null) ? (int) ($userOpacity * 100) : 70;
        } else {
            $opacity = (int) $rawOpacity;
        }
        
        // 5. Color: per-image → user global → default 'FFFFFF'
        if ($watermarkType === 'logo') {
            $color = 'FFFFFF'; 
            $ikFontSize = max(50, $fontSize);
        } else {
            $rawColor = $image->settings?->watermark_color;
            if (empty($rawColor)) {
                // User global color is stored as '#FFFFFF', strip the '#'
                $userColor = $userSettings?->watermark_text_color;
                $color = $userColor ? ltrim($userColor, '#') : 'FFFFFF';
            } else {
                $color = ltrim($rawColor, '#');
            }
            $ikFontSize = max(20, $fontSize);
        }

        // Apply opacity to color as hex alpha (e.g. FFFFFF + B3 for 70%)
        $alphaHex = str_pad(dechex(round($opacity / 100 * 255)), 2, '0', STR_PAD_LEFT);
        $colorWithAlpha = $color . $alphaHex;

        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
        if ($path) {
            $path = ltrim($path, '/');
            if (str_starts_with(strtolower($path), 'opticvault/')) {
                $path = substr($path, strlen('opticvault/'));
            }
        }

        // Generate signed watermarked URL via ImageKit with customization
        $url = $this->imageKit->getWatermarkedUrl($path, $watermarkText, true, 30, $ikFontSize, $colorWithAlpha, $watermarkType);

        \Illuminate\Support\Facades\Log::info("Generated Watermark URL for image {$image->id}: type={$watermarkType}, text='{$watermarkText}', fontSize={$ikFontSize}, opacity={$opacity}%, color=#{$colorWithAlpha}");

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
