<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Album;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * عرض صفحة إدارة الصور والألبومات
     */
    public function manage()
    {
        $images = Auth::user()->images()->latest()->get();
        $albums = Auth::user()->albums()->latest()->get();
        return view('images.index', compact('images', 'albums'));
    }

    /**
     * معالجة رفع صورة جديدة
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'title' => 'nullable|string|max:255',
        ]);

        $image = $this->imageService->upload($request->file('image'), $request->all(), Auth::id());

        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->tags));
            $image->attachTags($tags);
        }

        return back()->with('success', 'تم رفع الصورة ومعالجتها بنجاح!');
    }

    /**
     * حذف صورة
     */
    public function destroy(Image $image)
    {
        if ($image->user_id !== Auth::id()) {
            abort(403);
        }

        $this->imageService->delete($image);

        return back()->with('success', 'تم حذف الصورة بنجاح!');
    }

    /**
     * إنشاء ألبوم جديد (من صفحة إدارة الصور)
     */
    public function storeAlbum(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'is_private' => 'boolean',
        ]);

        Auth::user()->albums()->create([
            'title' => $validated['title'],
            'is_private' => $request->has('is_private'),
        ]);

        return back()->with('success', 'تم إنشاء الألبوم بنجاح!');
    }

    /**
     * عرض المعرض العام
     */
    public function gallery()
    {
        $images = Image::where('privacy', 'public')->latest()->paginate(12);
        return view('images.gallery', compact('images'));
    }
}
