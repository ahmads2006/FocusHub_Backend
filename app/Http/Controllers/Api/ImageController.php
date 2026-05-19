<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Http\Resources\PhotoResource;
use App\Services\Core\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index(Request $request)
    {
        $query = Image::query();

        if ($request->has('album_id')) {
            $album = \App\Models\Album::findOrFail($request->album_id);
            
            // Check if user has access to this album
            $this->authorize('view', $album);
            
            $query->where('album_id', $album->id);
        } else {
            // Global feed: only public images or user's own images
            $query->where(function ($q) {
                $q->where('privacy', 'public')
                  ->orWhere('user_id', Auth::id());
            });
        }

        $images = $query->latest()->paginate(20);
        return PhotoResource::collection($images);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|mimes:jpeg,png,jpg,webp,gif,heic,heif,tiff,tif,bmp,svg,jfif,pjpeg,pjp|max:25600',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_comparison' => 'boolean'
        ]);

        $user = Auth::user();
        
        if ($request->album_id) {
            $album = \App\Models\Album::findOrFail($request->album_id);
            $this->authorize('uploadPhoto', $album);
        }

        $file = $request->file('image');

        // ── Storage Quota Check (5GB Drive System) ──
        if (!$user->hasEnoughStorage($file->getSize())) {
            return response()->json([
                'message' => __('messages.storage_limit_exceeded')
            ], 403);
        }

        $image = $this->imageService->processAndUpload(
            $file, 
            $request->all(), 
            $user->id
        );

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'data' => $image,
            'url' => $image->url
        ], 201);
    }

    public function show(Request $request, Image $image)
    {
        // 🛡️ MULTI-LAYER AUTHORIZATION
        // 1. Check if user is normally authorized (owner, public, etc.)
        try {
            $this->authorize('view', $image);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // 2. Check for a valid sharing token if normal auth fails
            $token = $request->get('token');
            if (!$token) throw $e;

            // Search for an active shared link pointing to this image or its album
            // We use token_hash for secure database searching
            $tokenHash = hash('sha256', $token);
            $link = \App\Models\SharedLink::where('token_hash', $tokenHash)->first();
            if (!$link) {
                $persistentId = md5($tokenHash);
                $link = \App\Models\SharedLink::where('persistent_id', $persistentId)->first();
            }

            $isValid = false;
            if ($link && $link->is_active) {
                if ($link->shareable_type === \App\Models\Image::class) {
                    $isValid = ($link->shareable_id === $image->id);
                } elseif ($link->shareable_type === \App\Models\Album::class) {
                    $isValid = ($link->shareable_id === $image->album_id);
                }
            }
            
            if ($isValid) {
                $request->session()->put("shared_link_access_{$image->id}", $link->permission);
                $request->session()->put("shared_link_watermark_{$image->id}", $link->require_watermark);
                $request->session()->put("shared_link_id_{$image->id}", $link->id);
                if ($link->shareable_type === \App\Models\Album::class) {
                    $request->session()->put("shared_link_access_album_{$link->shareable_id}", $link->permission);
                }
            } else {
                throw $e;
            }
        }
        
        $image->load(['meta', 'user', 'tags', 'aiMetadata', 'settings']);
        
        // Get related images based on tags
        $tagNames = $image->tags->pluck('name')->toArray();
        
        $related = collect();
        if (!empty($tagNames)) {
            $related = Image::where('id', '!=', $image->id)
                ->where('privacy', 'public')
                ->withAnyTags($tagNames)
                ->with(['tags', 'settings'])
                ->withCount('analytics')
                ->get()
                ->map(function($rel) use ($tagNames) {
                    // Calculate match count
                    $relTags = $rel->tags->pluck('name')->toArray();
                    $rel->match_count = count(array_intersect($tagNames, $relTags));
                    return $rel;
                })
                ->sort(function($a, $b) {
                    // 1. Match count descending
                    if ($a->match_count !== $b->match_count) {
                        return $b->match_count <=> $a->match_count;
                    }
                    // 2. Popularity (analytics_count) descending
                    if ($a->analytics_count !== $b->analytics_count) {
                        return $b->analytics_count <=> $a->analytics_count;
                    }
                    // 3. Recency (created_at) descending
                    return $b->created_at <=> $a->created_at;
                })
                ->take(12)
                ->values();
        }

        $hasRelated = $related->isNotEmpty();

        if (!$hasRelated) {
            // Get random images if no related found
            $related = Image::where('id', '!=', $image->id)
                ->where('privacy', 'public')
                ->with(['settings'])
                ->withCount('analytics')
                ->inRandomOrder()
                ->take(12)
                ->get();
        }

        // Add display URLs to related images
        $related->each(function($img) {
            $img->append(['url', 'original_url']);
        });

        return response()->json([
            'image' => (new PhotoResource($image))->resolve(),
            'related' => PhotoResource::collection($related)->resolve(),
            'has_related' => $hasRelated,
            'match_base' => count($tagNames)
        ]);
    }

    public function update(Request $request, Image $image)
    {
        $this->authorize('update', $image);
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'is_comparison' => 'boolean',
            'allow_download' => 'boolean',
            'watermark_on_download' => 'boolean',
            'watermark_font_size' => 'nullable|integer|min:20|max:800',
            'watermark_opacity' => 'nullable|integer|min:10|max:100',
            'watermark_color' => 'nullable|string|max:8',
            'watermark_type' => 'nullable|string|in:text,logo',
            'watermark_text' => 'nullable|string|max:255',
        ]);

        $image->update(\Illuminate\Support\Arr::only($validated, ['title', 'description', 'privacy', 'album_id', 'is_comparison']));

        $settingsFields = ['allow_download', 'watermark_on_download', 'watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
        $settingsData = \Illuminate\Support\Arr::only($validated, $settingsFields);
        
        if (!empty($settingsData)) {
            $image->settings()->updateOrCreate([], $settingsData);
        }

        return response()->json([
            'message' => 'Image updated successfully.',
            'data' => $image->load(['settings', 'album'])
        ]);
    }

    public function destroy(Image $image)
    {
        $this->authorize('delete', $image);
        
        $this->imageService->delete($image);

        return response()->json([
            'message' => 'Image deleted successfully.'
        ]);
    }

    /**
     * Re-trigger AI analysis (Safety + Tagging) for a specific image.
     */
    public function retryScan(Image $image)
    {
        $this->authorize('update', $image);

        // Dispatch the AI analysis job to the queue
        \App\Jobs\AnalyzeImageLabelsJob::dispatch($image);

        return response()->json([
            'success' => true,
            'message' => __('messages.reanalyze_sent'),
        ]);
    }
}
