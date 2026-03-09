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
        $user = Auth::user();
        $images = $user->images()->latest()->get();
        $ownedAlbums = $user->ownedAlbums()->latest()->get();
        $sharedAlbums = $user->collaborativeAlbums()->latest()->get();
        
        return view('images.index', compact('images', 'ownedAlbums', 'sharedAlbums'));
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
            'auto_orient' => 'nullable|boolean',
        ]);

        if ($request->filled('album_id')) {
            $album = Album::findOrFail($request->album_id);
            if (Auth::user()->cannot('uploadPhoto', $album)) {
                return back()->withErrors(['album_id' => 'ليس لديك صلاحية لإضافة صور لهذا الألبوم.']);
            }
        }

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
        $this->authorize('delete', $image);

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
            'privacy' => 'nullable|in:public,private,hidden',
        ]);

        Auth::user()->ownedAlbums()->create([
            'title' => $validated['title'],
            'privacy' => $request->has('is_private') ? 'private' : ($validated['privacy'] ?? 'public'),
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
