<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\AI\RecommendationEngine;
use App\Services\Core\AssetDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    protected AssetDeliveryService $deliveryService;

    public function __construct(AssetDeliveryService $deliveryService)
    {
        $this->deliveryService = $deliveryService;
    }

    /**
     * Public gallery with smart search, tag filtering, and personalized feed.
     */
    public function index(Request $request): JsonResponse
    {
        $selectedTag = $request->query('tag');
        $searchQuery = $request->query('q');
        $perPage     = min($request->query('per_page', 50), 100);

        // Optimized Base Query (v31.0 Performance)
        $query = Image::select([
                'images.id', 'images.user_id', 'images.album_id', 'images.title', 
                'images.description', 'images.ai_description', 'images.filename', 
                'images.size', 'images.privacy', 'images.created_at', 'images.moderation_status'
            ])
            ->where('privacy', 'public')
            ->where('moderation_status', 'approved')
            ->with(['user.profile:id,user_id,username,avatar', 'storage:id,image_id,path,imagekit_file_id', 'album:id,title'])
            ->withCount('likes');

        // ── AI-Powered Smart Search (Optimized with Full-Text) ──
        if ($searchQuery) {
            $search = trim($searchQuery);
            $query->where(function ($q) use ($search) {
                // Use Full-Text for title/description (Super Fast)
                $q->whereFullText(['title', 'description'], $search)
                    ->orWhereRaw("JSON_SEARCH(labels, 'one', ?, NULL, '$[*]') IS NOT NULL", ["%{$search}%"])
                    ->orWhereHas('aiMetadata', function ($ai) use ($search) {
                        $ai->where('category', 'LIKE', "%{$search}%")
                            ->orWhere('caption', 'LIKE', "%{$search}%");
                    });
            });

            $images = $query->latest()->paginate($perPage);
        } elseif ($selectedTag) {
            $query->whereRaw('JSON_CONTAINS(labels, ?)', [json_encode($selectedTag)]);
            $images = $query->latest()->paginate($perPage);
        } else {
            // Personalized For You for authenticated users
            $user = Auth::user();
            if ($user) {
                try {
                    $recommendationEngine = app(RecommendationEngine::class);
                    $feedIds = $recommendationEngine->getForYouFeedIds($user, 500, true);
                } catch (\Exception $e) {
                    $feedIds = [];
                }

                $page       = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
                $totalCount = count($feedIds);
                $slicedIds  = array_slice($feedIds, ($page - 1) * $perPage, $perPage);

                if (!empty($slicedIds)) {
                    $placeholders = implode(',', array_fill(0, count($slicedIds), '?'));
                    $models = Image::select([
                            'images.id', 'images.user_id', 'images.album_id', 'images.title', 
                            'images.description', 'images.ai_description', 'images.filename', 
                            'images.size', 'images.privacy', 'images.created_at', 'images.moderation_status'
                        ])
                        ->whereIn('id', $slicedIds)
                        ->where('privacy', 'public')
                        ->where(function($q) use ($user) {
                            $q->where('moderation_status', 'approved')
                              ->orWhere(function($sq) use ($user) {
                                  $sq->where('user_id', $user->id)
                                     ->where('moderation_status', '!=', 'rejected');
                              });
                        })
                        ->with(['user.profile:id,user_id,username,avatar', 'storage:id,image_id,path,imagekit_file_id', 'album:id,title'])
                        ->withCount('likes')
                        ->orderByRaw("FIELD(id, {$placeholders})", $slicedIds)
                        ->get();
                } else {
                    // Fallback to latest images if no personalized feed
                    $models = $query->latest()->paginate($perPage)->getCollection();
                    $totalCount = $query->count();
                }

                $images = new \Illuminate\Pagination\LengthAwarePaginator(
                    $models, $totalCount, $perPage, $page,
                    ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
                );
            } else {
                $images = $query->latest()->paginate($perPage);
            }
        }

        // Interaction state for current user
        $likedImageIds     = [];
        $bookmarkedImageIds = [];
        if (Auth::check()) {
            $imageIds          = $images->pluck('id');
            $likedImageIds     = \App\Models\Like::where('user_id', Auth::id())
                ->whereIn('image_id', $imageIds)->pluck('image_id')->toArray();
            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', Auth::id())
                ->whereIn('image_id', $imageIds)->pluck('image_id')->toArray();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'images'               => $images,
                'liked_image_ids'      => $likedImageIds,
                'bookmarked_image_ids' => $bookmarkedImageIds,
                'filters'              => [
                    'tag'    => $selectedTag,
                    'search' => $searchQuery,
                ],
            ],
        ]);
    }

    /**
     * Get top popular tags from Redis Cache (refreshed every 6 hours)
     */
    public function popularTags(): JsonResponse
    {
        // Cache the result in Redis for 6 hours (21600 seconds)
        try {
            $popularTags = Cache::store('redis')->remember('gallery:popular_tags', 21600, function () {
                // Query Spatie tags table joined with taggables to count occurrences
                return DB::table('tags')
                    ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
                    ->select('tags.name', DB::raw('COUNT(taggables.tag_id) as usage_count'))
                    ->groupBy('tags.id', 'tags.name')
                    ->orderBy('usage_count', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(function ($tag) {
                        // Handle Spatie's JSON translatable names (fallback to extracting string if needed)
                        $decodedName = json_decode($tag->name, true);
                        $finalName = is_array($decodedName) ? ($decodedName['en'] ?? current($decodedName)) : $tag->name;
                        
                        return [
                            'name' => $finalName,
                            'count' => $tag->usage_count
                        ];
                    });
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis connection failed in popularTags: " . $e->getMessage());
            // Fallback to default cache or direct query if Redis is dead
            $popularTags = Cache::remember('gallery:popular_tags_fallback', 3600, function () {
                return DB::table('tags')
                    ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
                    ->select('tags.name', DB::raw('COUNT(taggables.tag_id) as usage_count'))
                    ->groupBy('tags.id', 'tags.name')
                    ->orderBy('usage_count', 'desc')
                    ->limit(20)
                    ->get()
                    ->map(function ($tag) {
                        $decodedName = json_decode($tag->name, true);
                        $finalName = is_array($decodedName) ? ($decodedName['en'] ?? current($decodedName)) : $tag->name;
                        return ['name' => $finalName, 'count' => $tag->usage_count];
                    });
            });
        }

        return response()->json([
            'success' => true,
            'data'    => $popularTags
        ]);
    }

    /**
     * Autocomplete tags based on user input query.
     */
    public function autocompleteTags(Request $request): JsonResponse
    {
        $query = trim($request->query('q', ''));
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Search inside Spatie tags
        $tags = DB::table('tags')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
            ->limit(10)
            ->get()
            ->map(function ($tag) {
                $decodedName = json_decode($tag->name, true);
                $finalName = is_array($decodedName) ? ($decodedName['en'] ?? current($decodedName)) : $tag->name;
                return ['name' => $finalName];
            })
            ->unique('name')
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $tags
        ]);
    }
}
