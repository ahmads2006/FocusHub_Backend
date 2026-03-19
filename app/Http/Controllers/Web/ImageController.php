<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Image;
use App\Models\Album;
use App\Models\ProtectedImage;
use App\Services\Core\ImageService;
use App\Services\Security\SecureShieldService;
use App\Services\Core\AssetDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected $imageService;
    protected $secureShield;
    protected $deliveryService;

    public function __construct(ImageService $imageService, SecureShieldService $secureShield, AssetDeliveryService $deliveryService)
    {
        $this->imageService    = $imageService;
        $this->secureShield    = $secureShield;
        $this->deliveryService = $deliveryService;
    }

    /**
     * عرض صفحة إدارة الصور والألبومات
     */
    public function manage()
    {
        $user = Auth::user();
        $images = $user->images()->withoutGlobalScope('visible')->latest()->get();
        $ownedAlbums = $user->ownedAlbums()->latest()->get();
        $sharedAlbums = $user->collaborativeAlbums()->latest()->get();

        // IDs of images that currently have an active SecureShield protected copy
        $protectedImageIds = ProtectedImage::whereIn('image_id', $images->pluck('id'))
            ->whereNull('reverted_at')
            ->pluck('image_id')
            ->flip()
            ->all();

        return view('images.index', compact('images', 'ownedAlbums', 'sharedAlbums', 'protectedImageIds'));
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
            'allow_download' => 'nullable|boolean',
            'watermark_on_download' => 'nullable|boolean',
        ]);

        if ($request->filled('album_id')) {
            $album = Album::findOrFail($request->album_id);
            if (Auth::user()->cannot('uploadPhoto', $album)) {
                return back()->withErrors(['album_id' => 'ليس لديك صلاحية لإضافة صور لهذا الألبوم.']);
            }
        }

        // The Full-Stack Cloud Pipeline (v7.0)
        // Handles: Local Extract -> Sanitization -> Cloud Upload -> Local Cleanup -> Database Save
        $image = $this->imageService->processAndUpload($request->file('image'), $request->all(), Auth::id());

        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->tags));
            $image->attachTags($tags);
        }

        // Auto-apply SecureShield watermark if the user enabled it at upload time
        if ($request->has('watermark_on_download')) {
            try {
                $this->secureShield->protect($image, [
                    'mode'              => 'signature',
                    'watermark_text'    => Auth::user()->name,
                    'digital_archiving' => true,
                ]);
            } catch (\Exception $e) {
                // Non-fatal: log and continue. The watermark will be generated on-the-fly at download.
                \Illuminate\Support\Facades\Log::warning('Auto SecureShield failed after upload: ' . $e->getMessage());
            }
        }

        if ($image->status === 'rejected') {
            return back()->with('error', 'تم حظر الصورة وحجبها تلقائياً لأنها تخالف سياسات الأمان الخاصة بالمنصة (محتوى غير لائق/عنيف).');
        } elseif ($image->status === 'pending_review') {
            return back()->with('warning', 'تم رفع الصورة، لكن تم إخفاؤها مؤقتاً لمراجعتها لاحتمالية احتوائها على محتوى حساس.');
        }

        return back()->with('success', 'تم رفع الصورة وتأمينها سحابياً بنجاح! 🛡');
    }

    /**
     * Secure Streamed Download (Path Masking)
     */
    public function download(Image $image)
    {
        $this->authorize('download', $image); // Use download policy instead of just view

        // Ensure shared link session doesn't interfere with this direct gallery download
        session()->forget("shared_link_access_{$image->id}");
        session()->forget("shared_link_watermark_{$image->id}");

        // Generate a secure, 5-minute signed URL for the original
        $url = $this->deliveryService->getUrl($image, 'original');

        return redirect($url);
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
     * تحديث معلومات الصورة (العنوان، الألبوم، الخصوصية)
     */
    public function update(Request $request, Image $image)
    {
        $this->authorize('update', $image);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'album_id' => 'nullable|exists:albums,id',
            'privacy' => 'nullable|in:public,private',
            'allow_download' => 'nullable|boolean',
        ]);

        if ($request->filled('album_id')) {
            $album = Album::findOrFail($request->album_id);
            if (Auth::user()->cannot('uploadPhoto', $album)) {
                return back()->withErrors(['album_id' => 'ليس لديك صلاحية لإضافة صور لهذا الألبوم.']);
            }
        }

        $image->update([
            'title' => $validated['title'] ?? $image->title,
            'album_id' => $request->has('album_id') ? $validated['album_id'] : $image->album_id,
            'privacy' => $validated['privacy'] ?? $image->privacy,
        ]);

        if ($request->has('allow_download')) {
            $image->settings()->update(['allow_download' => $request->boolean('allow_download')]);
        }

        return back()->with('success', 'تم تحديث معلومات الصورة بنجاح!');
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
     * تطبيق حماية SecureShield على الصورة
     */
    public function protect(Request $request, Image $image)
    {
        $this->authorize('update', $image);

        $validated = $request->validate([
            'mode' => 'required|in:signature,grid',
            'watermark_text' => 'nullable|string|max:100',
            'watermark_text_color' => 'nullable|string|max:7', // HEX color
            'watermark_neon_color' => 'nullable|string|max:7', // HEX color
            'watermark_opacity' => 'nullable|numeric|between:0,1',
            'watermark_logo' => 'nullable|file|mimes:jpeg,png,jpg,svg,webp|max:5120',
            // Keep others as optional/internal defaults
            'smart_positioning' => 'boolean',
            'dynamic_blending' => 'boolean',
            'digital_archiving' => 'boolean',
        ]);

        // Default settings for v2.0+ high-security layer
        $validated['smart_positioning'] = $request->input('smart_positioning', true);
        $validated['dynamic_blending'] = $request->input('dynamic_blending', true);
        $validated['digital_archiving'] = $request->input('digital_archiving', true);
        $validated['watermark_strength'] = 'medium';

        if ($request->hasFile('watermark_logo')) {
            $path = $request->file('watermark_logo')->store('watermarks', 'public');
            $validated['logo_path'] = $path;
        }

        $protectedUrl = $this->secureShield->protect($image, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Protection complete. Image secured and archived.',
            'url'     => $protectedUrl,
        ]);
    }

    /**
     * Non-destructively revert SecureShield protection.
     * Marks the active ProtectedImage row with reverted_at — the file stays on disk
     * but is no longer served as the protected download copy.
     */
    public function revert(Image $image)
    {
        $this->authorize('update', $image);

        $protected = ProtectedImage::where('image_id', $image->id)
            ->whereNull('reverted_at')
            ->latest()
            ->first();

        if (!$protected) {
            return response()->json([
                'success' => false,
                'message' => 'No active protection found for this image.',
            ], 404);
        }

        $protected->update(['reverted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Protection reverted. The original will now be served on download.',
        ]);
    }

    /**
     * عرض المعرض العام
     */
    public function gallery()
    {
        $images = Image::where('privacy', 'public')
            ->withCount('likes')
            ->latest()
            ->paginate(12);

        // Fetch which of these images the current user has already liked
        $likedImageIds = [];
        if (auth()->check()) {
            $likedImageIds = \App\Models\Like::where('user_id', auth()->id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')
                ->toArray();
        }

        return view('images.gallery', compact('images', 'likedImageIds'));
    }
}
