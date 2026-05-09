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

        // If user is owner, they can just download original directly without watermark if they want, 
        // but this route serves the watermarked version if requested.
        
        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;
        
        if (!$path) {
            abort(404, 'الصورة غير موجودة.');
        }

        $watermarkText = $image->user->name;

        // Generate signed watermarked URL via ImageKit
        $url = $this->imageKit->getWatermarkedUrl($path, $watermarkText, true, 30);
        // Add ik-attachment=true to force download
        $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . 'ik-attachment=true';

        return response()->json(['url' => $url]);
    }

    /**
     * Download original (no watermark)
     */
    public function downloadOriginal(Image $image)
    {
        // Only accessible if user has permission
        if (!$this->deliveryService->canAccessOriginal($image)) {
            abort(403, 'غير مصرح لك بتنزيل النسخة الأصلية من هذه الصورة.');
        }
        
        // Ensure shared link session doesn't interfere with this direct gallery download
        session()->forget("shared_link_access_{$image->id}");
        session()->forget("shared_link_watermark_{$image->id}");

        $url = $this->deliveryService->getUrl($image, 'original');

        return response()->json(['url' => $url]);
    }
}
