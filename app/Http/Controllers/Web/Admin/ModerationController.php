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
            ->whereIn('status', [Image::STATUS_PENDING_REVIEW, Image::STATUS_UNDER_REVIEW])
            ->with('user')
            ->latest()
            ->paginate(20);

        $reports = ImageReport::with(['user', 'image'])
            ->where('status', 'open')
            ->latest()
            ->paginate(20);

        return view('admin.moderation.index', compact('pendingImages', 'reports'));
    }

    public function approve(Image $image)
    {
        $image->withoutGlobalScopes()->update(['status' => Image::STATUS_APPROVED]);
        
        Log::info("Admin approved image: {$image->id}");

        return back()->with('success', __('تم اعتماد الصورة بنجاح.'));
    }

    public function reject(Image $image)
    {
        $image->withoutGlobalScopes()->update(['status' => Image::STATUS_REJECTED]);
        
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
