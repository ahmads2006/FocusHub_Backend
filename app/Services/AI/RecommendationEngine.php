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
     * Gets the personalized feed using the 50/20/30 distribution strategy.
     * - 50% Direct Match: Based on top 5 tags/creators (what user loves most).
     * - 20% Discovery Match: Based on secondary tags (6-15) to expand taste.
     * - 30% Random/Trending: Pure trending or random to break the filter bubble.
     */
    public function getForYouFeed(User $user, int $limit = 20, bool $includeOwnImages = false)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you:{$user->id}:" . ($includeOwnImages ? 'all' : 'others');

        // Use Redis Cache Tags tied to the user ID.
        $feed = Cache::tags(["user:{$user->id}", 'feeds'])->remember($cacheKey, 300, function () use ($user, $limit, $includeOwnImages) {
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // Cold Start Fallback — no prefs yet
            if (!$prefs || (empty($prefs->tag_weights) && empty($prefs->creator_weights))) {
                return $this->getTrendingFeed($limit, $includeOwnImages);
            }

            // --- Parse preference weights ---
            $tagWeights = $prefs->tag_weights ?? [];
            arsort($tagWeights);
            $allTagKeys = array_keys($tagWeights);

            $creatorWeights = $prefs->creator_weights ?? [];
            arsort($creatorWeights);
            $topCreators = array_keys(array_slice($creatorWeights, 0, 5, true));

            // --- Feed Bucket Sizes ---
            $directCount    = (int) ceil($limit * 0.50);  // 50%
            $discoveryCount = (int) ceil($limit * 0.20);  // 20%
            $randomCount    = $limit - $directCount - $discoveryCount; // 30%

            // --- Hidden images (not interested) from Redis ---
            $hiddenIds = Redis::smembers("hidden_images:{$user->id}") ?? [];

            // Base query constraints (shared across all buckets)
            $baseConstraints = function ($q) use ($user, $hiddenIds, $includeOwnImages) {
                $q->where('privacy', 'public')
                  ->whereDoesntHave('likes', fn($lq) => $lq->where('user_id', $user->id));
                
                if (!$includeOwnImages) {
                    $q->where('user_id', '!=', $user->id);
                }

                if (!empty($hiddenIds)) {
                    $q->whereNotIn('id', $hiddenIds);
                }
            };

            // ── BUCKET 1: 50% Direct Match (top 5 tags + top creators) ──────
            $topTags = array_slice($allTagKeys, 0, 5);
            $directResults = collect();
            if (!empty($topTags) || !empty($topCreators)) {
                $q = Image::query()->tap($baseConstraints);
                if (!empty($topTags)) {
                    $json = json_encode($topTags);
                    $q->whereRaw("JSON_OVERLAPS(JSON_EXTRACT(labels, '$[*].description'), ?) OR JSON_OVERLAPS(labels, ?)", [$json, $json]);
                }
                if (!empty($topCreators)) {
                    $placeholders = implode(',', array_fill(0, count($topCreators), '?'));
                    $q->orderByRaw("FIELD(user_id, {$placeholders}) DESC", $topCreators);
                }
                $directResults = $q->latest()->take($directCount)->get();
            }

            // ── BUCKET 2: 20% Discovery Match (secondary tags 6-15) ──────────
            $secondaryTags = array_slice($allTagKeys, 5, 10);
            $discoveryResults = collect();
            if (!empty($secondaryTags)) {
                $json = json_encode($secondaryTags);
                $excludeIds = array_merge($hiddenIds, $directResults->pluck('id')->toArray());
                $discoveryResults = Image::query()
                    ->tap($baseConstraints)
                    ->whereNotIn('id', $excludeIds)
                    ->whereRaw("JSON_OVERLAPS(JSON_EXTRACT(labels, '$[*].description'), ?) OR JSON_OVERLAPS(labels, ?)", [$json, $json])
                    ->latest()
                    ->take($discoveryCount)
                    ->get();
            }

            $excludeIds = array_merge(
                $hiddenIds,
                $directResults->pluck('id')->toArray(),
                $discoveryResults->pluck('id')->toArray()
            );

            // ── BUCKET 3: Trending / Latest Fallback (Fill the remaining slots) ──
            // Dynamically calculate remaining slots to ensure we always hit the $limit
            $remainingCount = max(0, $limit - $directResults->count() - $discoveryResults->count());

            // Fetch images based on likes count, then latest date, excluding already shown
            $randomResults = Image::query()
                ->tap($baseConstraints)
                ->whereNotIn('id', $excludeIds)
                ->withCount('likes')
                ->orderBy('likes_count', 'desc')
                ->latest()
                ->take($remainingCount)
                ->get();
            
            // Absolute fallback: if still empty, try pure random
            if ($randomResults->isEmpty()) {
                $randomResults = Image::query()
                    ->tap($baseConstraints)
                    ->whereNotIn('id', $excludeIds)
                    ->inRandomOrder()
                    ->take($remainingCount)
                    ->get();
            }

            // ── Merge and shuffle to make feed look natural ───────────────────
            return $directResults
                ->merge($discoveryResults)
                ->merge($randomResults)
                ->unique('id')
                ->shuffle()
                ->values();
        });

        // 3. Emit Feed Datadog Metrics
        $latencyMs = (microtime(true) - $startTime) * 1000;
        $this->emitDatadogMetric('distribution', 'opticvault.recommendation.latency', $latencyMs);
        
        // Track Hit Rate simply: if latency is very low (< 5ms), it was likely a Cache Hit
        $status = $latencyMs < 10 ? 'hit' : 'miss';
        $this->emitDatadogMetric('count', 'opticvault.redis.hit_rate', 1, ["status:{$status}"]);

        return $feed;
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
}

