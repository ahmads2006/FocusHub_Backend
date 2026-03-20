<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppealController extends Controller
{
    /**
     * Display a listing of the user's appeals (Appeal History).
     */
    public function index()
    {
        $appeals = ImageAppeal::with(['image'])->where('user_id', Auth::id())->latest()->paginate(15);
        return view('appeals.index', compact('appeals'));
    }

    /**
     * Show the form for creating a new appeal for a specific image.
     */
    public function create(Image $image)
    {
        // Must be the owner to appeal
        if ($image->user_id !== Auth::id()) {
            abort(403, 'غير مصرح لك بتقديم طلب مراجعة لهذه الصورة.');
        }

        // Check if image is actually rejected
        if ($image->status !== 'rejected') {
            return redirect()->route('images.gallery')->with('error', 'هذه الصورة ليست محظورة لطلب مراجعتها.');
        }

        // Check if there is already a pending appeal
        if ($image->appeals()->where('status', 'pending')->exists()) {
            return redirect()->route('images.gallery')->with('warning', 'يوجد طلب مراجعة قيد الانتظار حالياً لهذه الصورة.');
        }

        // Check if there is already a rejected appeal (One-Appeal Limit)
        if ($image->appeals()->where('status', 'rejected')->exists()) {
            return redirect()->route('images.gallery')->with('error', 'تم رفض طلب المراجعة مسبقاً لهذه الصورة. قرار الإدارة نهائي ولا يمكن تقديم طلب آخر.');
        }

        return view('appeals.create', compact('image'));
    }

    /**
     * Store a newly created appeal in storage.
     */
    public function store(Request $request, Image $image)
    {
        if ($image->user_id !== Auth::id()) {
            abort(403);
        }

        // Check if image is actually rejected
        if ($image->status !== 'rejected') {
            return back()->with('error', 'لا يمكن تقديم طلب مراجعة إلا للصور المحظورة.');
        }

        // Check if an appeal already exists that is pending
        if ($image->appeals()->where('status', 'pending')->exists()) {
            return back()->with('error', 'يوجد طلب مراجعة قيد الانتظار حالياً لهذه الصورة.');
        }

        // Check if there is already a rejected appeal (One-Appeal Limit)
        if ($image->appeals()->where('status', 'rejected')->exists()) {
            return back()->with('error', 'عذراً، لا يمكنك تقديم طلب مراجعة آخر لهذه الصورة بعد رفضه من الإدارة.');
        }

        $validated = $request->validate([
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'reason' => 'required|string|min:10|max:1000',
        ]);

        $appeal = new ImageAppeal([
            'user_id' => Auth::id(),
            'contact_name' => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        $image->appeals()->save($appeal);

        // TODO: Send notification to admins
        
        return redirect()->route('images.index')->with('success', 'تم إرسال طلب المراجعة بنجاح سيتم فحصه من قبل الإدارة.');
    }
}
