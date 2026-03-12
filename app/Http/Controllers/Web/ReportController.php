<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function image(Request $request, Image $image)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate reporting by the same user
        $exists = ImageReport::where('user_id', Auth::id())
            ->where('image_id', $image->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return back()->with('info', __('لقد قمت بالتبليغ عن هذه الصورة مسبقاً.'));
        }

        ImageReport::create([
            'user_id' => Auth::id(),
            'image_id' => $image->id,
            'reason' => $request->reason,
            'details' => $request->details,
        ]);

        // Auto-Hide Logic: If reports >= 3, move to under_review
        $reportCount = $image->reports()->where('status', 'open')->count();
        if ($reportCount >= 3 && $image->status === Image::STATUS_APPROVED) {
            $image->update(['status' => Image::STATUS_UNDER_REVIEW]);
        }

        return back()->with('success', __('شكرًا لك. تم استلام بلاغك وسيقوم فريق الإشراف بمراجعته.'));
    }
}
