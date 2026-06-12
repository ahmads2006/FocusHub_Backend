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
        $this->validateTokenAndSetSession($image);

        $this->authorize('download', $image);

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
        
        // ─── LOGO VALIDATION: ImageKit l-image requires the logo to exist in ImageKit Media Library.
        //     If logo path is invalid (blob URL, default placeholder, empty), gracefully fall back to text.
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
                // Abort download if the user specified a logo watermark but no valid logo is available
                \Illuminate\Support\Facades\Log::error("Download blocked: invalid logo path '{$logoPath}'.");
                abort(400, 'لا يمكن تنزيل الصورة لأن  مسارها غير صالح. يرجى إعادة رفع اللوغو في الإعدادات. ');
            }
        }
        
        if (in_array($watermarkType, ['text', 'sig', 'glass'])) {
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

        $this->validateTokenAndSetSession($image);

        $this->authorize('download', $image);

        // Only accessible if user has permission
        if (!$this->deliveryService->canAccessOriginal($image)) {
            abort(403, 'غير مصرح لك بتنزيل النسخة الأصلية من هذه الصورة.');
        }

        // 🛡️ WATERMARK ENFORCEMENT: If the owner enabled watermark_on_download
        // or the shared link requires watermark, force watermarked download (unless owner).
        $image->load('settings');
        
        $hasWatermark = false;
        if (session()->has("shared_link_watermark_{$image->id}")) {
            $hasWatermark = (bool) session("shared_link_watermark_{$image->id}");
        } else {
            $hasWatermark = (bool) ($image->settings?->watermark_on_download ?? false);
        }

        $isOwner = \Illuminate\Support\Facades\Auth::id() === $image->user_id;

        if ($hasWatermark && !$isOwner) {
            \Illuminate\Support\Facades\Log::info("Watermark enforced for non-owner on image {$image->id}");
            return $this->download($image);
        }

        $url = $this->deliveryService->getUrl($image, 'original');

        return response()->json(['url' => $url]);
    }

    /**
     * Helper to validate sharing token and inject into session
     */
    private function validateTokenAndSetSession(Image $image): void
    {
        $token = request()->get('token');
        if ($token) {
            $tokenHash = hash('sha256', $token);
            $link = \App\Models\SharedLink::where('token_hash', $tokenHash)->first();
            if (!$link) {
                $persistentId = md5($tokenHash);
                $link = \App\Models\SharedLink::where('persistent_id', $persistentId)->first();
            }

            $isValid = false;
            if ($link && $link->is_active) {
                if ($link->shareable_type === \App\Models\Image::class) {
                    $isValid = ($link->shareable_id === $image->id);
                } elseif ($link->shareable_type === \App\Models\Album::class) {
                    $isValid = ($link->shareable_id === $image->album_id);
                }
            }

            if ($isValid) {
                session()->put("shared_link_access_{$image->id}", $link->permission);
                session()->put("shared_link_watermark_{$image->id}", $link->require_watermark);
                session()->put("shared_link_id_{$image->id}", $link->id);
                if ($link->shareable_type === \App\Models\Album::class) {
                    session()->put("shared_link_access_album_{$link->shareable_id}", $link->permission);
                }
            }
        }
    }
}
