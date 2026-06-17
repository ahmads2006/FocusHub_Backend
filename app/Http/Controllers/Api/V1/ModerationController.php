<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use App\Notifications\ImageStatusNotification;
use App\Notifications\ReportStatusNotification;
use App\Services\Core\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ModerationController extends Controller
{
    public function __construct(
        protected ImageService $imageService
    ) {}

    /**
     * List pending images and open reports (admin).
     */
    public function index(): JsonResponse
    {
        $pendingImages = Image::withoutGlobalScopes()
            ->whereHas('moderation', function ($query) {
                $query->where('status', Image::STATUS_PENDING_REVIEW);
            })
            ->with(['user', 'moderation', 'storage'])
            ->latest()
            ->paginate(20);

        $mediumImages = Image::withoutGlobalScopes()
            ->whereHas('moderation', function ($query) {
                $query->where('status', Image::STATUS_UNDER_REVIEW);
            })
            ->with(['user', 'moderation', 'storage'])
            ->latest()
            ->paginate(20);

        $safeImages = Image::withoutGlobalScopes()
            ->where('privacy', 'public')
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'approved');
            })
            ->with(['user', 'moderation', 'storage'])
            ->latest()
            ->paginate(20);

        $bannedImages = Image::withoutGlobalScopes()
            ->whereHas('moderation', function ($query) {
                $query->where('status', 'rejected');
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
                'medium_images'  => $mediumImages,
                'safe_images'    => $safeImages,
                'banned_images'  => $bannedImages,
                'reports'        => $reports,
            ],
        ]);
    }

    /**
     * Approve an image (admin).
     */
    public function approve($image): JsonResponse
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);
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

        // Notify image owner
        if ($image->user) {
            $msg = __('لقد تمت الموافقة على صورتك ":title" من قبل فريق الإشراف.', ['title' => $image->title]);
            $image->user->notify(new ImageStatusNotification($image, Image::STATUS_APPROVED, $msg));
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.image_approved'),
        ]);
    }

    /**
     * Acknowledge sensitive content (admin) — stays yellow in gallery, moves to "medium" queue.
     */
    public function markSensitive($image): JsonResponse
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);

        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'       => Image::STATUS_UNDER_REVIEW,
            'is_sensitive' => true,
            'is_visible'   => true,
        ]);

        $image->withoutGlobalScopes()->touch();
        Log::info("Admin marked image as acknowledged-sensitive: {$image->id}");

        return response()->json([
            'success' => true,
            'message' => __('messages.image_marked_sensitive'),
        ]);
    }

    /**
     * Reject an image (admin).
     */
    public function reject($image): JsonResponse
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);
        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'     => Image::STATUS_REJECTED,
            'is_visible' => false,
        ]);

        $image->withoutGlobalScopes()->touch();
        Log::info("Admin rejected image: {$image->id}");

        // Notify image owner
        if ($image->user) {
            $msg = __('تم رفض صورتك ":title" بسبب مخالفتها لمعايير المجتمع.', ['title' => $image->title]);
            $image->user->notify(new ImageStatusNotification($image, Image::STATUS_REJECTED, $msg));
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.image_rejected'),
        ]);
    }

    /**
     * Resolve a community report (admin).
     * - resolve: delete image + storage, notify owner & reporter
     * - dismiss: politely notify reporter only
     */
    public function resolveReport(ImageReport $report, string $action): JsonResponse
    {
        if (!in_array($action, ['resolve', 'dismiss'], true)) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid action.'),
            ], 422);
        }

        if ($report->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => __('messages.report_already_processed'),
            ], 422);
        }

        $report->loadMissing(['user', 'image.user', 'image.storage']);

        try {
            if ($action === 'dismiss') {
                return DB::transaction(function () use ($report) {
                    $report->update(['status' => 'dismissed']);

                    if ($report->user) {
                        $report->user->notify(new ReportStatusNotification(
                            $report,
                            'dismissed',
                            __('messages.report_dismissed_reporter')
                        ));
                    }

                    Log::info("Admin dismissed report #{$report->id} for image {$report->image_id}");

                    return response()->json([
                        'success' => true,
                        'message' => __('messages.report_dismissed_admin'),
                    ]);
                });
            }

            return DB::transaction(function () use ($report) {
                $image = Image::withoutGlobalScopes()
                    ->with(['user', 'storage'])
                    ->find($report->image_id);

                $imageId = $report->image_id;
                $owner = $image?->user;
                $reporter = $report->user;
                $imageTitle = $image?->title ?: $imageId;

                // 1. Send notifications FIRST while the database records and relations are fully intact
                if ($image && $owner) {
                    $owner->notify(new ImageStatusNotification(
                        $image,
                        Image::STATUS_REJECTED,
                        __('messages.report_resolved_owner', ['title' => $imageTitle])
                    ));
                }

                if ($reporter) {
                    $reporter->notify(new ReportStatusNotification(
                        $report,
                        'resolved',
                        __('messages.report_resolved_reporter')
                    ));
                }

                // 2. Delete the image (this will delete the image and cascade delete reports referencing it)
                if ($image) {
                    $this->imageService->delete($image);
                    Log::info("Admin accepted report #{$report->id} — deleted image {$imageId}");
                } else {
                    Log::warning("Admin accepted report #{$report->id} — image {$imageId} already missing");
                }

                // 3. Update status and refresh report only if it still exists in the database
                // (It won't exist if the image was successfully deleted due to onDelete('cascade') constraint)
                if (ImageReport::where('id', $report->id)->exists()) {
                    ImageReport::where('image_id', $imageId)
                        ->where('status', 'open')
                        ->update(['status' => 'resolved']);
                    
                    $report->refresh();
                }

                return response()->json([
                    'success' => true,
                    'message' => __('messages.report_image_removed_admin'),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error("Failed to resolve report #{$report->id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => __('Failed to process report. Please try again.'),
            ], 500);
        }
    }
}
