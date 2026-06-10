<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ModerationController extends Controller
{
    public function index()
    {
        $pendingImages = Image::withoutGlobalScopes()
            ->whereHas('moderation', function ($query) {
                $query->where('status', Image::STATUS_PENDING_REVIEW);
            })
            ->with(['user', 'moderation', 'storage'])
            ->latest()
            ->paginate(20);

        $reports = ImageReport::with(['user', 'image.storage', 'image.settings'])
            ->where('status', 'open')
            ->latest()
            ->paginate(20);

        return view('admin.moderation.index', compact('pendingImages', 'reports'));
    }

    public function approve(Request $request, $image)
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);
        $image->loadMissing(['moderation', 'storage']);

        // If image was sensitive (yellow), restore the clean original from secure_uploads
        if ($image->is_sensitive && $image->storage?->original_path) {
            try {
                $originalContents = \Illuminate\Support\Facades\Storage::disk('local')
                    ->get($image->storage->original_path);

                if ($originalContents && $image->storage->path) {
                    \Illuminate\Support\Facades\Storage::disk('public')
                        ->put($image->storage->path, $originalContents);

                    // Clear ImageKit references so it falls back to the restored local file
                    if (!empty($image->storage->imagekit_file_path)) {
                        $image->storage()->update([
                            'imagekit_file_id'   => null,
                            'imagekit_file_path' => null,
                        ]);
                    }

                    Log::info("Admin restored original clean image from secure_uploads for: {$image->id}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to restore original image for {$image->id}: " . $e->getMessage());
            }
        }

        // Update moderation status via the relationship
        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'       => Image::STATUS_APPROVED,
            'is_sensitive' => false,
            'is_visible'   => true,
        ]);

        // Touch the image updated_at for cache-busting
        $image->withoutGlobalScopes()->touch();

        Log::info("Admin approved image: {$image->id}");

        return back()->with('success', __('تم اعتماد الصورة بنجاح. تم استعادة النسخة الأصلية النقية.'));
    }

    public function markSensitive($image)
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);

        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'       => Image::STATUS_UNDER_REVIEW,
            'is_sensitive' => true,
            'is_visible'   => true,
        ]);

        $image->withoutGlobalScopes()->touch();
        Log::info("Admin marked image as acknowledged-sensitive: {$image->id}");

        return back()->with('success', __('messages.image_marked_sensitive'));
    }

    public function reject($image)
    {
        $image = Image::withoutGlobalScopes()->findOrFail($image);
        $image->moderation()->updateOrCreate(['image_id' => $image->id], [
            'status'     => Image::STATUS_REJECTED,
            'is_visible' => false,
        ]);
        $image->withoutGlobalScopes()->touch();
        
        Log::info("Admin rejected image: {$image->id}");

        return back()->with('warning', __('تم رفض وحجب الصورة.'));
    }

    public function banUser(User $user)
    {
        $user->userStatus()->update([
            'is_banned' => true,
            'banned_at' => now(),
            'ban_reason' => 'Multiple violations of community standards.'
        ]);

        Log::warning("Admin banned user: {$user->id}");

        return back()->with('error', __('تم حظر المستخدم نهائياً.'));
    }

    public function resolveReport(ImageReport $report, string $action)
    {
        if ($action === 'dismiss') {
            $report->update(['status' => 'dismissed']);
        } else {
            $report->update(['status' => 'resolved']);
        }

        return back()->with('success', __('تم تحديث حالة البلاغ.'));
    }
}
