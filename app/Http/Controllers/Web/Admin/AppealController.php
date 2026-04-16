<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImageAppeal;
use App\Models\Image;
use App\Mail\AppealApprovedMail;
use App\Mail\AppealRejectedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AppealController extends Controller
{
    public function index()
    {
        $appeals = ImageAppeal::with(['user', 'image', 'reviewer'])->latest()->paginate(20);
        return view('admin.appeals.index', compact('appeals'));
    }

    public function approve(Request $request, ImageAppeal $appeal)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000'
        ]);

        $appeal->update([
            'status' => 'approved',
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // Unban the image
        if ($appeal->image) {
            $appeal->image->moderation()->update([
                'status' => 'approved',
                'is_visible' => true,
                'is_sensitive' => false,
            ]);
        }

        if ($appeal->contact_email) {
            Mail::to($appeal->contact_email)->queue(new AppealApprovedMail($appeal));
        }

        return back()->with('success', 'تم قبول طلب المراجعة وإلغاء حظر الصورة بنجاح و إرسال رسالة بريدية لـ' . $appeal->contact_name);
    }

    public function reject(Request $request, ImageAppeal $appeal)
    {
        $validated = $request->validate([
            'admin_notes' => 'required|string|max:1000'
        ]);

        $appeal->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // Image remains rejected

        if ($appeal->contact_email) {
            Mail::to($appeal->contact_email)->queue(new AppealRejectedMail($appeal));
        }

        return back()->with('success', 'تم رفض طلب المراجعة وبقاء حظر الصورة وإرسال بريد إلكتروني لـ' . $appeal->contact_name);
    }
}
