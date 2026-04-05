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
        $images = $user->images()->with(['settings', 'storage'])->withoutGlobalScope('visible')->latest()->get();
        $ownedAlbums = $user->ownedAlbums()->latest()->get();
        $sharedAlbums = $user->collaborativeAlbums()->wherePivot('status', 'accepted')->latest()->get();

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
            'image' => 'required|image|mimes:jpeg,png,jpg,webp,gif,heic,heif|max:10240',
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
                    'watermark_text'    => Auth::user()->watermark_text ?? Auth::user()->name,
                    'watermark_text_color' => Auth::user()->watermark_text_color ?? '#ffffff',
                    'watermark_neon_color' => Auth::user()->watermark_neon_color ?? '#800080',
                    'watermark_opacity'    => Auth::user()->watermark_opacity ?? 0.8,
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
            'base_title' => 'nullable|string|max:255',
            'album_id' => 'nullable|exists:albums,id',
            'privacy' => 'nullable|in:public,private',
            'allow_download' => 'nullable|boolean',
            'watermark_on_download' => 'nullable|boolean',
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

        // Invalidate Feed Cache so privacy changes show up instantly in their own gallery
        app(\App\Services\AI\RecommendationEngine::class)->invalidateUserFeedCache(auth()->id());

        // Checkboxes: handle both checked (sent) and unchecked (not sent)
        $image->settings()->updateOrCreate(
            ['image_id' => $image->id],
            [
                'allow_download' => $request->boolean('allow_download'),
                'watermark_on_download' => $request->boolean('watermark_on_download'),
            ]
        );

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
    public function gallery(Request $request)
    {
        $selectedTag = $request->query('tag');
        $searchQuery = $request->query('q');
        
        // Setup base query for filtering or guest users
        $query = Image::where('privacy', 'public')
            ->with(['settings', 'user', 'labelData', 'aiMetadata', 'storage'])
            ->withCount('likes');

        // --- AI-Powered Smart Search ---
        if ($searchQuery) {
            $search = trim($searchQuery);
            $query->where(function($q) use ($search) {
                // 1. Search in title
                $q->where('title', 'LIKE', "%{$search}%")
                  // 2. Search in description
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  // 3. Search in legacy labels JSON column
                  ->orWhereRaw("JSON_SEARCH(labels, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$search}%"])
                  // 4. Search in AI metadata (category, caption, extracted_tags)
                  ->orWhereHas('aiMetadata', function($ai) use ($search) {
                      $ai->where('category', 'LIKE', "%{$search}%")
                         ->orWhere('caption', 'LIKE', "%{$search}%")
                         ->orWhereRaw("JSON_SEARCH(extracted_tags, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$search}%"]);
                  })
                  // 5. Search in Spatie tags
                  ->orWhereHas('tags', function($t) use ($search) {
                      $t->where('name->en', 'LIKE', "%{$search}%")
                        ->orWhere('name->ar', 'LIKE', "%{$search}%")
                        ->orWhere('name', 'LIKE', "%{$search}%");
                  });
            });

            $images = $query->latest()->paginate(20);
        } elseif ($selectedTag) {
            $query->whereRaw('JSON_CONTAINS(labels, ?)', [json_encode($selectedTag)]);
            $images = $query->latest()->paginate(20);
        } else {
            // No tag selected. If user is logged in, use personalized For You engine
            $user = auth()->user();
            if ($user) {
                // Fetch up to 500 personalized image IDs (Extremely fast, low RAM)
                $recommendationEngine = app(\App\Services\AI\RecommendationEngine::class);
                $feedIds = $recommendationEngine->getForYouFeedIds($user, 500, true);

                // Manual pagination of the lightweight integer array
                $perPage = 20;
                $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
                $totalCount = count($feedIds);

                // Slice only the IDs needed for THIS specific page
                $slicedIds = array_slice($feedIds, ($page - 1) * $perPage, $perPage);

                // Hydrate ONLY the 20 models needed for this page
                if (!empty($slicedIds)) {
                    $placeholders = implode(',', array_fill(0, count($slicedIds), '?'));
                    $models = Image::whereIn('id', $slicedIds)
                        ->where('privacy', 'public') // Prevents stale cache from exposing newly-private images
                        ->with(['settings', 'user', 'labelData', 'aiMetadata', 'storage'])
                        ->withCount('likes')
                        ->orderByRaw("FIELD(id, {$placeholders})", $slicedIds)
                        ->get();
                } else {
                    $models = collect();
                }

                $images = new \Illuminate\Pagination\LengthAwarePaginator(
                    $models,
                    $totalCount,
                    $perPage,
                    $page,
                    ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
                );
            } else {
                // Guests fallback to latest timeline
                $images = $query->latest()->paginate(20);
            }
        }

        // Fetch which of these images the current user has already liked or bookmarked
        $likedImageIds = [];
        $bookmarkedImageIds = [];
        if (auth()->check()) {
            $likedImageIds = \App\Models\Like::where('user_id', auth()->id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')
                ->toArray();
                
            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', auth()->id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')
                ->toArray();
        }

        $categories = [
            'nature' => 'طبيعة',
            'mountains' => 'جبال',
            'technology' => 'تكنولوجيا',
            'houses' => 'منازل',
            'forests' => 'غابات',
            'ocean' => 'بحر',
            'architecture' => 'عمارة',
            'electronics' => 'إلكترونيات',
            'humans' => 'اشخاص',
            // ... more categories
        ];
        $categories = collect($categories)->sortKeys();
        
        // Infinite Scroll AJAX Response
        if ($request->ajax()) {
            /** @var \Illuminate\View\View $view */
            $view = view('images.gallery', compact('images', 'likedImageIds', 'bookmarkedImageIds', 'categories', 'selectedTag', 'searchQuery'));
            return response()->json([
                'grid_html' => $view->fragment('grid-items'),
                'list_html' => $view->fragment('list-items'),
                'has_more' => $images->hasMorePages(),
                'next_page_url' => $images->nextPageUrl(),
            ]);
        }

        return view('images.gallery', compact('images', 'likedImageIds', 'bookmarkedImageIds', 'categories', 'selectedTag', 'searchQuery'));
    }
}
