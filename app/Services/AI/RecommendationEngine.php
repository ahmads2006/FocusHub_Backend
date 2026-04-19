<?php

namespace App\Services\AI;

use App\Jobs\UpdateUserPreferencesJob;
use App\Models\Image;
use App\Models\Like;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RecommendationEngine
{
    /**
     * Toggles a 'Like' on an image and fires background preference jobs.
     */
    public function toggleLike(User $user, Image $image): array
    {
        $action = DB::transaction(function () use ($user, $image) {
            // Use pessimistic locking to prevent race conditions
            $like = Like::where('user_id', $user->id)
                ->where('image_id', $image->id)
                ->lockForUpdate()
                ->first();

            if ($like) {
                // Unlike: remove it
                $like->delete();
                return 'unlike';
            } else {
                // Like: create it (unique constraint in DB prevents double likes)
                Like::create([
                    'user_id'  => $user->id,
                    'image_id' => $image->id,
                ]);
                return 'like';
            }
        });

        // Redis operations outside of DB transaction to prevent rollbacks on Redis failure
        try {
            Redis::zincrby('trending_images_24h', ($action === 'like' ? 1 : -1), $image->id);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Redis zincrby failed for image {$image->id}: " . $e->getMessage());
        }

        $weightAdjustment = ($action === 'like') ? 1 : -1;

        // Emit Datadog Action Counter
        $this->emitDatadogMetric('count', 'opticvault.likes.action', 1, ["type:{$action}"]);

        // ── Instant Cache Invalidation via Redis Tags ───────────────────
        // Immediately flush this user's feed cache so the effect of their
        // Like/Unlike is visible on the very next page load, without waiting
        // for the background UpdateUserPreferencesJob to finish.
        $this->invalidateUserFeedCache($user->id);

        // Dispatch background job to update JSON affinities
        UpdateUserPreferencesJob::dispatch($user->id, $image->id, $weightAdjustment);

        // Emit Datadog Gauge for Trending Set Size (Resilient to Redis failure)
        try {
            $trendingSize = Redis::zcard('trending_images_24h');
            $this->emitDatadogMetric('gauge', 'opticvault.trending.total_size', $trendingSize);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis zcard failed: " . $e->getMessage());
        }

        return ['success' => true, 'action' => $action];
    }

    /**
     * Gets the personalized feed IDs using the 50/20/30 distribution strategy.
     * Caches lightweight integer arrays instead of full heavy Models.
     */
    public function getForYouFeedIds(User $user, int $limit = 500, bool $includeOwnImages = false)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you_ids:{$user->id}:" . ($includeOwnImages ? 'all' : 'others') . ":v10"; // Version bump

        // Cache tied to user ID. Reduced to 30 seconds so pulling-to-refresh quickly yields a new Pinterest-style feed.
        $feedIds = Cache::tags(["user:{$user->id}", 'feeds'])->remember($cacheKey, 30, function () use ($user, $limit, $includeOwnImages) {
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // --- Hidden images (not interested) from Redis ---
            $hiddenIds = [];
            try {
                $hiddenIds = Redis::smembers("hidden_images:{$user->id}") ?? [];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Redis smembers failed for user {$user->id}: " . $e->getMessage());
            }

            // Base query constraints for UNLIKED items (Discovery Phase)
            $unlikedConstraints = function ($q) use ($user, $hiddenIds, $includeOwnImages) {
                $q->where('privacy', 'public')
                  ->whereDoesntHave('likes', fn($lq) => $lq->where('user_id', $user->id));
                
                if (!$includeOwnImages) {
                    $q->where('user_id', '!=', $user->id);
                }

                if (!empty($hiddenIds)) {
                    $q->whereNotIn('id', $hiddenIds);
                }
            };

            // Bucket Sizes (Aim for the limit with unliked content first)
            $directCount    = (int) ceil($limit * 0.50);  // 50%
            $discoveryCount = (int) ceil($limit * 0.20);  // 20%
            $randomCount    = $limit - $directCount - $discoveryCount; // 30%

            // 1. Parse Preferences
            $tagWeights = $prefs->tag_weights ?? [];
            arsort($tagWeights);
            $allTagKeys = array_keys($tagWeights);
            $topTags = array_slice($allTagKeys, 0, 5);
            $secondaryTags = array_slice($allTagKeys, 5, 10);

            $creatorWeights = $prefs->creator_weights ?? [];
            arsort($creatorWeights);
            $topCreators = array_keys(array_slice($creatorWeights, 0, 5, true));

            // ── BUCKET 1: Direct Match (Unliked) ──
            $directResults = [];
            if (!empty($topTags) || !empty($topCreators)) {
                $q = Image::query()->tap($unlikedConstraints);
                if (!empty($topTags)) {
                    // Global Architecture Upgrade: Using Spatie's Many-to-Many Pivot Table instead of Slow JSON!
                    $q->withAnyTags($topTags);
                }
                if (!empty($topCreators)) {
                    $placeholders = implode(',', array_fill(0, count($topCreators), '?'));
                    $q->orderByRaw("FIELD(user_id, {$placeholders}) DESC", $topCreators);
                }
                // Fetch a larger pool and shuffle for Pinterest-like randomization, then crop to needed count
                $directResults = $q->latest()->take($directCount * 4)->get(['id', 'user_id', 'labels'])->shuffle()->take($directCount)->values()->toArray();
            }

            $directIds = array_column($directResults, 'id');

            // ── BUCKET 2: Discovery Match (Unliked) ──
            $discoveryResults = [];
            if (!empty($secondaryTags)) {
                $excludeIds = array_merge($hiddenIds, $directIds);
                $discoveryResults = Image::query()
                    ->tap($unlikedConstraints)
                    ->whereNotIn('id', $excludeIds)
                    ->withAnyTags($secondaryTags) // Uses Pivot Tables instead of JSON
                    ->latest()
                    ->take($discoveryCount * 4)
                    ->get(['id', 'user_id', 'labels'])
                    ->shuffle()
                    ->take($discoveryCount)
                    ->values()
                    ->toArray();
            }

            $discoveryIds = array_column($discoveryResults, 'id');

            // ── BUCKET 3: Fresh/Latest Content (Seed traffic for new uploads) ──
            $excludeIds = array_merge($hiddenIds, $directIds, $discoveryIds);
            $remainingFreshCount = max(0, $limit - count($directResults) - count($discoveryResults));
            
            $freshAllocation = (int) ceil($remainingFreshCount * 0.4); // 40% of leftover strictly for newness

            $freshResults = Image::query()
                ->tap($unlikedConstraints)
                ->whereNotIn('id', $excludeIds)
                ->latest() // Strictly newest first, no sorting by likes
                ->take((int)($freshAllocation * 4))
                ->get(['id', 'user_id', 'labels'])
                ->shuffle()
                ->take($freshAllocation)
                ->values()
                ->toArray();

            $freshIds = array_column($freshResults, 'id');
            $excludeIds = array_merge($excludeIds, $freshIds);

            // ── BUCKET 4: Trending Fallback (Unliked, sorted by likes) ──
            $trendingAllocation = max(0, $remainingFreshCount - count($freshResults));
            $trendingResults = Image::query()
                ->tap($unlikedConstraints)
                ->whereNotIn('id', $excludeIds)
                ->withCount('likes')
                ->orderBy('likes_count', 'desc')
                ->latest()
                ->take((int)($trendingAllocation * 4))
                ->get(['id', 'user_id', 'labels'])
                ->shuffle()
                ->take($trendingAllocation)
                ->values()
                ->toArray();

            // ── BUCKET 5: Historical Likes (Show last) ──
            // ADVICE: In modern apps (like TikTok), we DO NOT show liked content in the main feed 
            // to keep it focused on discovery. Users should go to their profile to see 'Liked' items.
            // Leaving it empty to improve algorithmic engagement.
            $likedResults = [];

            // Merge unliked models into a pool (Keeps priority: Direct -> Discovery -> Fresh -> Trending)
            $freshDiscoveryPool = array_merge($directResults, $discoveryResults, $freshResults, $trendingResults);
            
            // Uniquify based on ID
            $uniquePool = [];
            $seenIds = [];
            foreach ($freshDiscoveryPool as $item) {
                if (!isset($seenIds[$item['id']])) {
                    $seenIds[$item['id']] = true;
                    $uniquePool[] = $item;
                }
            }

            // Apply Smart Spacing (Anti-Clustering)
            $spacedIds = $this->smartSpaceItems($uniquePool);

            // Return as simple array of IDs
            return array_values(array_unique(array_merge($spacedIds, $likedResults)));
        });

        // 3. Emit Feed Datadog Metrics
        $latencyMs = (microtime(true) - $startTime) * 1000;
        $this->emitDatadogMetric('distribution', 'opticvault.recommendation.latency', $latencyMs);
        
        $status = $latencyMs < 10 ? 'hit' : 'miss';
        $this->emitDatadogMetric('count', 'opticvault.redis.hit_rate', 1, ["status:{$status}"]);

        return $feedIds;
    }

    /**
     * Highly optimized Feed Hydration with Pagination support.
     * Slices the pre-calculated ID pool in memory to avoid redundant AI logic.
     */
    public function getForYouFeed(User $user, int $limit = 50, bool $includeOwnImages = false, int $page = 1)
    {
        // 1. Get the full pre-calculated pool (500 IDs max, cached for 30s)
        $allIds = $this->getForYouFeedIds($user, 500, $includeOwnImages);
        
        if (empty($allIds)) return collect();

        // 2. Calculate the slice (Pagination in memory)
        // If page=1, limit=20 -> offset=0
        // If page=2, limit=20 -> offset=20
        $offset = ($page - 1) * $limit;
        $slicedIds = array_slice($allIds, $offset, $limit);

        if (empty($slicedIds)) return collect();

        // 3. Hydrate only the sliced chunk from DB (Uses Primary Key Index - Ultra Fast)
        $placeholders = implode(',', array_fill(0, count($slicedIds), '?'));
        
        return Image::whereIn('id', $slicedIds)
            ->where('privacy', 'public')
            ->where('moderation_status', 'approved')
            ->with(['settings', 'user', 'labelData', 'storage'])
            ->withCount('likes')
            ->orderByRaw("FIELD(id, {$placeholders})", $slicedIds)
            ->get();
    }

    /**
     * Ultra-fast Cold Start using Redis ZREVRANGE or Cached Query.
     */
    protected function getTrendingFeed(int $limit = 20, bool $includeOwnImages = false)
    {
        $cacheKey = "feed:trending:" . ($includeOwnImages ? 'all' : 'others') . ":limit_{$limit}";

        return Cache::remember($cacheKey, 60, function () use ($limit, $includeOwnImages) {
            $query = Image::where('privacy', 'public')
                ->where('moderation_status', 'approved') // Added: Only show approved images for performance & safety
                ->with(['storage', 'settings', 'user'])
                ->withCount('likes');

            if (!$includeOwnImages && auth()->check()) {
                $query->where('user_id', '!=', auth()->id());
            }

            return $query->orderBy('likes_count', 'desc')
                ->latest()
                ->take($limit)
                ->get();
        });
    }

    /**
     * Helpers for Datadog Mocking (You would use an official StatsD library here)
     */
    protected function emitDatadogMetric(string $type, string $metric, $value, array $tags = [])
    {
        $tagString = implode(',', $tags);
        Log::channel('datadog')->debug("Datadog Metric [{$type}]", [
            'metric' => $metric,
            'value' => $value,
            'tags' => $tagString
        ]);
        
        // E.g., if using php-datadogstatsd package:
        // if ($type === 'count') \DataDog\DogStatsd::increment($metric, $value, $tags);
        // if ($type === 'gauge') \DataDog\DogStatsd::gauge($metric, $value, $tags);
        // if ($type === 'distribution') \DataDog\DogStatsd::distribution($metric, $value, $tags);
    }

    /**
     * Instantly invalidates the 'For You' feed for a specific user using Redis tags.
     */
    public function invalidateUserFeedCache($userId): void
    {
        try {
            Cache::tags(["user:{$userId}", 'feeds'])->flush();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to invalidate user feed cache for {$userId}: " . $e->getMessage());
        }
    }

    /**
     * Smart Spacing Algorithm (Anti-Clustering)
     * Distributes images so that the same creator or the same primary tag
     * does not appear consecutively in the feed.
     */
    protected function smartSpaceItems(array $items)
    {
        $buffer = []; 
        $penaltyBox = []; 
        $lastCreatorId = null;
        $lastPrimaryTag = null;
        
        // We DO NOT shuffle here to preserve the Priority Buckets (Direct > Discovery > Fresh)
        // Shuffling would destroy the 50/20/30 distribution weighting.
        $itemsCollection = $items; 

        while (!empty($itemsCollection) || !empty($penaltyBox)) {
            $placed = false;
            
            // Try to place an item from the main pool
            foreach ($itemsCollection as $index => $item) {
                // Determine primary tag
                $primaryTag = null;
                $labels = is_string($item['labels'] ?? null) ? json_decode($item['labels'], true) : ($item['labels'] ?? []);
                if (!empty($labels)) {
                    $firstLabel = $labels[0];
                    $primaryTag = is_string($firstLabel) ? $firstLabel : ($firstLabel['description'] ?? null);
                }

                // Check conflict
                $creatorConflict = ($item['user_id'] === $lastCreatorId);
                $tagConflict = ($primaryTag !== null && $primaryTag === $lastPrimaryTag);

                if (!$creatorConflict && (!$tagConflict || empty($primaryTag))) {
                    $buffer[] = $item['id'];
                    $lastCreatorId = $item['user_id'];
                    $lastPrimaryTag = $primaryTag;
                    unset($itemsCollection[$index]);
                    $itemsCollection = array_values($itemsCollection);
                    $placed = true;
                    break;
                }
            }

            if (!$placed && !empty($penaltyBox)) {
                // Try penalty box
                foreach ($penaltyBox as $index => $item) {
                    $primaryTag = null;
                    $labels = is_string($item['labels'] ?? null) ? json_decode($item['labels'], true) : ($item['labels'] ?? []);
                    if (!empty($labels)) {
                        $firstLabel = $labels[0];
                        $primaryTag = is_string($firstLabel) ? $firstLabel : ($firstLabel['description'] ?? null);
                    }

                    $creatorConflict = ($item['user_id'] === $lastCreatorId);
                    $tagConflict = ($primaryTag !== null && $primaryTag === $lastPrimaryTag);

                    if (!$creatorConflict && (!$tagConflict || empty($primaryTag))) {
                        $buffer[] = $item['id'];
                        $lastCreatorId = $item['user_id'];
                        $lastPrimaryTag = $primaryTag;
                        unset($penaltyBox[$index]);
                        $penaltyBox = array_values($penaltyBox);
                        $placed = true;
                        break;
                    }
                }
            }

            // If we are absolutely stuck, force insert to keep moving
            if (!$placed) {
                if (!empty($itemsCollection)) {
                    // Pull item and force it into the feed buffer (FIX: Removed duplicate insertion into penaltyBox)
                    $item = array_shift($itemsCollection);
                    $buffer[] = $item['id'];
                    $lastCreatorId = $item['user_id'];
                    
                    $labels = is_string($item['labels'] ?? null) ? json_decode($item['labels'], true) : ($item['labels'] ?? []);
                    $lastPrimaryTag = null;
                    if (!empty($labels)) {
                        $firstLabel = $labels[0];
                        $lastPrimaryTag = is_string($firstLabel) ? $firstLabel : ($firstLabel['description'] ?? null);
                    }
                } elseif (!empty($penaltyBox)) {
                    $item = array_shift($penaltyBox);
                    $buffer[] = $item['id'];
                    $lastCreatorId = $item['user_id'];
                    
                    $labels = is_string($item['labels'] ?? null) ? json_decode($item['labels'], true) : ($item['labels'] ?? []);
                    $lastPrimaryTag = null;
                    if (!empty($labels)) {
                        $firstLabel = $labels[0];
                        $lastPrimaryTag = is_string($firstLabel) ? $firstLabel : ($firstLabel['description'] ?? null);
                    }
                }
            }
        }

        return $buffer;
    }
}

