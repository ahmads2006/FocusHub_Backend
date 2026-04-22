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
                    'user_id' => $user->id,
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
    public function getForYouFeedIds(User $user, int $limit = 500, bool $includeOwnImages = false, int $blockIndex = 0)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you_ids:{$user->id}:" . ($includeOwnImages ? 'all' : 'others') . ":block:{$blockIndex}:v11";

        // Cache tied to user ID. Reduced to 30 seconds so pulling-to-refresh quickly yields a new Pinterest-style feed.
        $feedIds = Cache::tags(["user:{$user->id}", 'feeds'])->remember($cacheKey, 30, function () use ($user, $limit, $includeOwnImages, $blockIndex) {
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // --- Hidden images (not interested OR already seen) from Redis ---
            $hiddenIds = [];
            try {
                // Get explicitly hidden images
                $dislikedIds = Redis::smembers("hidden_images:{$user->id}") ?? [];

                // Get images seen in the last 7 days (Using ZSET v2 for memory capping)
                $seenIdsV2 = Redis::zrange("seen_images_v2:{$user->id}", 0, -1) ?? [];
                
                // Fallback: Also merge legacy v1 seen_images during the 20-day transition period
                $legacySeenIds = Redis::smembers("seen_images:{$user->id}") ?? [];

                $hiddenIds = array_unique(array_merge($dislikedIds, $seenIdsV2, $legacySeenIds));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Redis smembers failed for user {$user->id}: " . $e->getMessage());
            }

            // Base query constraints for UNLIKED items (Discovery Phase)
            $unlikedConstraints = function ($q) use ($user, $hiddenIds, $includeOwnImages) {
                $q->where('privacy', 'public')
                    ->where('moderation_status', 'approved')
                    ->whereDoesntHave('likes', fn($lq) => $lq->where('user_id', $user->id));

                if (!$includeOwnImages) {
                    $q->where('user_id', '!=', $user->id);
                }

                if (!empty($hiddenIds)) {
                    $q->whereNotIn('id', $hiddenIds);
                }
            };

            // Bucket Sizes (Aim for the limit with unliked content first)
            $directCount = (int) ceil($limit * 0.50);  // 50%
            $discoveryCount = (int) ceil($limit * 0.20);  // 20%
            $randomCount = $limit - $directCount - $discoveryCount; // 30%

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
                ->take((int) ($freshAllocation * 4))
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
                ->take((int) ($trendingAllocation * 4))
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
            $finalIds = array_values(array_unique(array_merge($spacedIds, $likedResults)));

            // --- 🛡️ INTELLIGENT TIERED QUALITY FALLBACK (النظام الطبقي المتكامل) ---
            if (count($finalIds) < $limit) {
                $needed = $limit - count($finalIds);
                $fallbackOffset = $blockIndex * $needed;

                // Tier 2a: Personalized Old Content (Images seen in last 20 days but match user interests)
                $personalizedOldIds = [];
                if (!empty($topTags)) {
                    $personalizedOldIds = Image::where('privacy', 'public')
                        ->where('moderation_status', 'approved')
                        ->whereNotIn('id', $finalIds)
                        ->withAnyTags($topTags)
                        ->withCount('likes')
                        ->orderBy('likes_count', 'desc')
                        ->offset($fallbackOffset)
                        ->take($needed)
                        ->pluck('id')
                        ->toArray();
                    
                    $finalIds = array_merge($finalIds, $personalizedOldIds);
                }

                // Tier 2b: General High-Quality Fallback (If still needed)
                if (count($finalIds) < $limit) {
                    $stillNeeded = $limit - count($finalIds);
                    $extraIds = Image::where('privacy', 'public')
                        ->where('moderation_status', 'approved')
                        ->whereNotIn('id', $finalIds)
                        ->withCount('likes')
                        ->orderBy('likes_count', 'desc')
                        ->offset($fallbackOffset)
                        ->take($stillNeeded)
                        ->pluck('id')
                        ->toArray();

                    $finalIds = array_merge($finalIds, $extraIds);
                }
            }

            return array_values(array_unique($finalIds));
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
        // 1. Calculate which "Block" of 500 images the user is currently in.
        // Block 0: Images 1-500 (Pages 1-5 if limit=100)
        // Block 1: Images 501-1000 (Pages 6-10)
        $batchSize = 500;
        $blockIndex = (int) floor((($page - 1) * $limit) / $batchSize);

        // 2. Get IDs for this specific block (Cached for 30s per block)
        $allIds = $this->getForYouFeedIds($user, $batchSize, $includeOwnImages, $blockIndex);

        if (empty($allIds))
            return collect();

        // 3. Calculate the slice relative to the current block
        // Example: Page 6, Limit 100 -> Offset 500. Offset in Block 1 is 0.
        $offsetInBlock = (($page - 1) * $limit) % $batchSize;
        $slicedIds = array_slice($allIds, $offsetInBlock, $limit);

        if (empty($slicedIds))
            return collect();

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
     * Advanced Anti-Clustering Algorithm.
     * Ensures visual diversity by spacing out items from the same creator or with the same primary tags.
     */
    protected function smartSpaceItems(array $items): array
    {
        if (empty($items))
            return [];

        $buffer = [];
        $itemsCollection = $items;
        $penaltyBox = [];

        $lastCreatorId = null;
        $lastPrimaryTag = null;

        $maxAttempts = count($items) * 2;
        $attempts = 0;

        while ((!empty($itemsCollection) || !empty($penaltyBox)) && $attempts < $maxAttempts) {
            $attempts++;
            $placed = false;

            // Try to pull from main collection first
            foreach ($itemsCollection as $index => $item) {
                $primaryTag = $this->getPrimaryTag($item);

                $creatorConflict = ($item['user_id'] === $lastCreatorId);
                $tagConflict = ($primaryTag !== null && $primaryTag === $lastPrimaryTag);

                if (!$creatorConflict && !$tagConflict) {
                    $buffer[] = $item['id'];
                    $lastCreatorId = $item['user_id'];
                    $lastPrimaryTag = $primaryTag;
                    unset($itemsCollection[$index]);
                    $itemsCollection = array_values($itemsCollection);
                    $placed = true;
                    break;
                } else {
                    // Move to penalty box if it conflicts
                    $penaltyBox[] = $item;
                    unset($itemsCollection[$index]);
                    $itemsCollection = array_values($itemsCollection);
                }
            }

            // If we couldn't place from main, try the penalty box (but with relaxed rules if needed)
            if (!$placed && !empty($penaltyBox)) {
                foreach ($penaltyBox as $index => $item) {
                    $primaryTag = $this->getPrimaryTag($item);
                    $creatorConflict = ($item['user_id'] === $lastCreatorId);

                    // In penalty box, we only care about creator conflict to avoid complete deadlocks
                    if (!$creatorConflict) {
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

            // Absolute Fallback: If still stuck, just take the first one available to prevent deadlocks
            if (!$placed) {
                $item = !empty($itemsCollection) ? array_shift($itemsCollection) : array_shift($penaltyBox);
                if ($item) {
                    $buffer[] = $item['id'];
                    $lastCreatorId = $item['user_id'];
                    $lastPrimaryTag = $this->getPrimaryTag($item);
                }
            }
        }

        return $buffer;
    }

    /**
     * Helper to extract the primary tag for anti-clustering logic.
     */
    protected function getPrimaryTag(array $item): ?string
    {
        $labels = is_string($item['labels'] ?? null) ? json_decode($item['labels'], true) : ($item['labels'] ?? []);
        if (empty($labels))
            return null;

        $firstLabel = $labels[0];
        return is_string($firstLabel) ? $firstLabel : ($firstLabel['description'] ?? null);
    }
}

