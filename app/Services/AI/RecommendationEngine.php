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
                Redis::zincrby('trending_images_24h', -1, $image->id);
                return 'unlike';
            } else {
                // Like: create it (unique constraint in DB prevents double likes)
                Like::create([
                    'user_id'  => $user->id,
                    'image_id' => $image->id,
                ]);
                Redis::zincrby('trending_images_24h', 1, $image->id);
                return 'like';
            }
        });

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

        // Emit Datadog Gauge for Trending Set Size
        $trendingSize = Redis::zcard('trending_images_24h');
        $this->emitDatadogMetric('gauge', 'opticvault.trending.total_size', $trendingSize);

        return ['success' => true, 'action' => $action];
    }

    /**
     * Gets the personalized feed IDs using the 50/20/30 distribution strategy.
     * Caches lightweight integer arrays instead of full heavy Models.
     */
    public function getForYouFeedIds(User $user, int $limit = 500, bool $includeOwnImages = false)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you_ids:{$user->id}:" . ($includeOwnImages ? 'all' : 'others') . ":v9"; // Version bump for ID logic

        // Use Redis Cache Tags tied to the user ID.
        $feedIds = Cache::tags(["user:{$user->id}", 'feeds'])->remember($cacheKey, 300, function () use ($user, $limit, $includeOwnImages) {
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // --- Hidden images (not interested) from Redis ---
            $hiddenIds = Redis::smembers("hidden_images:{$user->id}") ?? [];

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
                    $json = json_encode($topTags);
                    $q->whereRaw("JSON_OVERLAPS(JSON_EXTRACT(labels, '$[*].description'), ?) OR JSON_OVERLAPS(labels, ?)", [$json, $json]);
                }
                if (!empty($topCreators)) {
                    $placeholders = implode(',', array_fill(0, count($topCreators), '?'));
                    $q->orderByRaw("FIELD(user_id, {$placeholders}) DESC", $topCreators);
                }
                $directResults = $q->latest()->take($directCount)->get(['id', 'user_id', 'labels'])->toArray();
            }

            $directIds = array_column($directResults, 'id');

            // ── BUCKET 2: Discovery Match (Unliked) ──
            $discoveryResults = [];
            if (!empty($secondaryTags)) {
                $json = json_encode($secondaryTags);
                $excludeIds = array_merge($hiddenIds, $directIds);
                $discoveryResults = Image::query()
                    ->tap($unlikedConstraints)
                    ->whereNotIn('id', $excludeIds)
                    ->whereRaw("JSON_OVERLAPS(JSON_EXTRACT(labels, '$[*].description'), ?) OR JSON_OVERLAPS(labels, ?)", [$json, $json])
                    ->latest()
                    ->take($discoveryCount)
                    ->get(['id', 'user_id', 'labels'])
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
                ->take($freshAllocation)
                ->get(['id', 'user_id', 'labels'])
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
                ->take($trendingAllocation)
                ->get(['id', 'user_id', 'labels'])
                ->toArray();

            // ── BUCKET 5: Historical Likes (Show last) ──
            $likedResults = Image::query()
                ->where('privacy', 'public')
                ->whereHas('likes', fn($lq) => $lq->where('user_id', $user->id))
                ->latest()
                ->take(100) 
                ->pluck('id')
                ->toArray();

            // Merge unliked models into a pool
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

            // Apply Smart Spacing
            $spacedIds = $this->smartSpaceItems($uniquePool);

            // Append Liked content at the end and return as simple array of IDs
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
     * Backward-compatible method returning fully hydrated Models for API endpoints
     */
    public function getForYouFeed(User $user, int $limit = 500, bool $includeOwnImages = false)
    {
        $ids = $this->getForYouFeedIds($user, $limit, $includeOwnImages);
        if (empty($ids)) return collect();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Image::whereIn('id', $ids)
            ->where('privacy', 'public') // Prevents stale cache from exposing newly-private images
            ->with(['settings', 'user', 'labelData'])
            ->withCount('likes')
            ->orderByRaw("FIELD(id, {$placeholders})", $ids)
            ->get();
    }

    /**
     * Ultra-fast Cold Start using Redis ZREVRANGE.
     */
    protected function getTrendingFeed(int $limit = 20, bool $includeOwnImages = false)
    {
        $query = Image::where('privacy', 'public')
            ->withCount('likes');

        // Optional: Include owner images in Gallery context
        if (!$includeOwnImages && auth()->check()) {
            $query->where('user_id', '!=', auth()->id());
        }

        // Logic: 
        // 1. Order by Likes (most popular first, even if old)
        // 2. Order by Date (if same likes, newest first)
        return $query->orderBy('likes_count', 'desc')
            ->latest()
            ->take($limit)
            ->get();
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
        Cache::tags(["user:{$userId}", 'feeds'])->flush();
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
        
        // Ensure random initial distribution before intelligent sorting
        $itemsCollection = collect($items)->shuffle()->all();

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

                if (!$creatorConflict && !$tagConflict) {
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

                    if (!$creatorConflict && !$tagConflict) {
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
                    $item = array_shift($itemsCollection);
                    $penaltyBox[] = $item; // Wait, actually just force into buffer
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

