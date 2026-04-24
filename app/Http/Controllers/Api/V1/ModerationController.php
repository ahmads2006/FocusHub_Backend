<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModerationController extends Controller
{
    /**
     * List pending images and open reports (admin).
     */
    public function index(): JsonResponse
    {
        $pendingImages = Image::withoutGlobalScopes()
            ->where('privacy', 'public')
            ->whereHas('moderation', function ($query) {
                $query->whereIn('status', [Image::STATUS_PENDING_REVIEW, Image::STATUS_UNDER_REVIEW]);
            })
            ->with(['user', 'moderation', 'storage'])
            ->latest()
            ->paginate(20);

        $reports = ImageReport::with(['user', 'image.storage', 'image.settings'])
            ->where('status', 'open')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => [
                'pending_images' => $pendingImages,
                'reports'        => $reports,
            ],
        ]);
    }

    /**
     * Approve an image (admin).
     */
    public function approve(Image $image): JsonResponse
    {
        $image->loadMissing(['moderation', 'storage']);

        // If image was sensitive, restore from secure_uploads
        if ($image->is_sensitive && $image->storage?->original_path) {
            try {
                $originalContents = Storage::disk('local')->get($image->storage->original_path);
                if ($originalContents && $image->storage->path) {
                    Storage::disk('public')->put($image->storage->path, $originalContents);

                    if (!empty($image->storage->imagekit_file_path)) {
                        $image->storage()->update([
                            'imagekit_file_id'   => null,
                            'imagekit_file_path' => null,
                        ]);
                    }
                    Log::info("Admin restored original clean image for: {$image->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to restore original image for {$image->id}: " . $e->getMessage());
            }
        }

        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'       => Image::STATUS_APPROVED,
            'is_sensitive' => false,
            'is_visible'   => true,
        ]);

        $image->withoutGlobalScopes()->touch();
        Log::info("Admin approved image: {$image->id}");

        return response()->json([
            'success' => true,
            'message' => 'تم اعتماد الصورة بنجاح.',
        ]);
    }

    /**
     * Reject an image (admin).
     */
    public function reject(Image $image): JsonResponse
    {
        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'     => Image::STATUS_REJECTED,
            'is_visible' => false,
        ]);

        $image->withoutGlobalScopes()->touch();
        Log::info("Admin rejected image: {$image->id}");

        return response()->json([
            'success' => true,
            'message' => 'تم رفض وحجب الصورة.',
        ]);
    }

    /**
     * Resolve a report (admin).
     */
    public function resolveReport(ImageReport $report, string $action): JsonResponse
    {
        if ($action === 'dismiss') {
            $report->update(['status' => 'dismissed']);
        } else {
            $report->update(['status' => 'resolved']);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث حالة البلاغ.',
        ]);
    }
}
