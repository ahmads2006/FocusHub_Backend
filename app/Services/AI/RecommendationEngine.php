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
    public function getForYouFeed(User $user, int $limit = 20)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you:{$user->id}";

        $feed = Cache::remember($cacheKey, 300, function () use ($user, $limit) {
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // Cold Start Fallback — no prefs yet
            if (!$prefs || (empty($prefs->tag_weights) && empty($prefs->creator_weights))) {
                return $this->getTrendingFeed($limit);
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
            $baseConstraints = function ($q) use ($user, $hiddenIds) {
                $q->where('visibility', 'public')
                  ->where('user_id', '!=', $user->id)
                  ->whereDoesntHave('likes', fn($lq) => $lq->where('user_id', $user->id));
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

            // ── BUCKET 3: 30% Trending / Random ─────────────────────────────
            $excludeIds = array_merge(
                $hiddenIds,
                $directResults->pluck('id')->toArray(),
                $discoveryResults->pluck('id')->toArray()
            );
            $trendingIds = Redis::zrevrange('trending_images_24h', 0, ($randomCount * 3) - 1);
            $trendingIds = array_diff($trendingIds, $excludeIds);

            $randomResults = collect();
            if (!empty($trendingIds)) {
                $placeholders = implode(',', array_fill(0, count($trendingIds), '?'));
                $randomResults = Image::whereIn('id', $trendingIds)
                    ->where('visibility', 'public')
                    ->whereNotIn('id', $excludeIds)
                    ->orderByRaw("FIELD(id, {$placeholders})", $trendingIds)
                    ->take($randomCount)
                    ->get();
            }

            // Fallback: if trending is empty, use pure random
            if ($randomResults->isEmpty()) {
                $randomResults = Image::query()
                    ->tap($baseConstraints)
                    ->whereNotIn('id', array_merge($hiddenIds, $directResults->pluck('id')->toArray(), $discoveryResults->pluck('id')->toArray()))
                    ->inRandomOrder()
                    ->take($randomCount)
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
    protected function getTrendingFeed(int $limit = 20)
    {
        // Get highest scored images instantly
        $trendingIds = Redis::zrevrange('trending_images_24h', 0, $limit - 1);
        
        if (empty($trendingIds)) {
            // Absolute absolute fallback: latest good images
            return Image::where('visibility', 'public')->latest()->take($limit)->get();
        }

        // Fetch from DB honoring order using FIELD()
        $placeholders = implode(',', array_fill(0, count($trendingIds), '?'));
        return Image::whereIn('id', $trendingIds)
            ->where('visibility', 'public')
            ->orderByRaw("FIELD(id, {$placeholders})", $trendingIds)
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
}
