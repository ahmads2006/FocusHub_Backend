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
     * Gets the highly personalized feed using Redis caching, Cold Start fallback, and optimized JSON matching.
     */
    public function getForYouFeed(User $user, int $limit = 20)
    {
        $startTime = microtime(true);
        $cacheKey = "feed:for_you:{$user->id}";

        $feed = Cache::remember($cacheKey, 300, function () use ($user, $limit) { // 5 minutes TTL
            $prefs = UserPreference::where('user_id', $user->id)->first();

            // 1. Cold Start Fallback (No preferences or completely empty weights)
            if (!$prefs || (empty($prefs->tag_weights) && empty($prefs->creator_weights))) {
                return $this->getTrendingFeed($limit);
            }

            // 2. Optimized Query Logic
            // Parse top 5 preferred tags to keep query optimal
            $tagWeights = $prefs->tag_weights ?? [];
            arsort($tagWeights);
            $topTags = array_keys(array_slice($tagWeights, 0, 5, true));

            $creatorWeights = $prefs->creator_weights ?? [];
            arsort($creatorWeights);
            $topCreators = array_keys(array_slice($creatorWeights, 0, 5, true));
            
            // If the user has preferences but they happened to all be zero/deleted
            if (empty($topTags) && empty($topCreators)) {
                return $this->getTrendingFeed($limit);
            }

            // Query using JSON_OVERLAPS for fast performance on MySQL 8.0+
            $query = Image::query()
                ->where('visibility', 'public') // Only public images
                ->where('user_id', '!=', $user->id) // Don't show their own images
                ->whereDoesntHave('likes', function($q) use ($user) {
                    $q->where('user_id', $user->id); // Don't show already liked images
                });

            if (!empty($topTags)) {
                $topTagsJson = json_encode($topTags);
                // Assume images.labels is a JSON array. JSON_OVERLAPS returns 1 if they intersect.
                // Depending on the exact structure, we handle simple JSON matching. 
                // Using raw expression for JSON_OVERLAPS:
                $query->whereRaw("JSON_OVERLAPS(JSON_EXTRACT(labels, '$[*].description'), ?) OR JSON_OVERLAPS(labels, ?)", [$topTagsJson, $topTagsJson]);
                
                // For demonstration of ORDER BY RAW, we elevate specific creators too
                if (!empty($topCreators)) {
                    $creatorsCsv = "'" . implode("','", $topCreators) . "'";
                    $query->orderByRaw("FIELD(user_id, {$creatorsCsv}) DESC");
                }
            } else if (!empty($topCreators)) {
                 $query->whereIn('user_id', $topCreators);
            }

            $results = $query->latest()->take($limit)->get();

            // Fallback if the personalized query yields too few results
            if ($results->count() < ($limit / 2)) {
                $additional = $this->getTrendingFeed($limit - $results->count());
                // Ensure unique collection
                $results = $results->merge($additional)->unique('id');
            }

            return $results;
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
        $idsCsv = "'" . implode("','", $trendingIds) . "'";
        return Image::whereIn('id', $trendingIds)
            ->where('visibility', 'public')
            ->orderByRaw("FIELD(id, {$idsCsv})")
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
