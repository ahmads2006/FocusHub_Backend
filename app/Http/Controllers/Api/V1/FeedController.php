<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\UpdateUserPreferencesJob;
use App\Models\Image;
use App\Models\UserHiddenImage;
use App\Notifications\ImageSocialNotification;
use App\Services\AI\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class FeedController extends Controller
{
    protected RecommendationEngine $engine;

    public function __construct(RecommendationEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Get the highly personalized "Home" feed (General Gallery).
     */
    public function home(Request $request): JsonResponse
    {
        $limit = min($request->get('per_page', 50), 100); // Default 50, Max 100
        $page  = max($request->get('page', 1), 1);
        $user  = Auth::user();

        // Pass the page to the engine for memory-slicing
        $feed = $this->engine->getForYouFeed($user, $limit, false, $page);

        $likedImageIds = [];
        $bookmarkedImageIds = [];
        if ($user) {
            $imageIds = $feed->pluck('id');
            $likedImageIds = \App\Models\Like::where('user_id', $user->id)
                ->whereIn('image_id', $imageIds)->pluck('image_id')->toArray();
            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', $user->id)
                ->whereIn('image_id', $imageIds)->pluck('image_id')->toArray();
        }

        $imageIds = $feed->pluck('id')->toArray();
        if (!empty($imageIds) && $user) {
            try {
                // Changed to ZSET (v2) to support memory capping (max 2000 items)
                $redisKey = "seen_images_v2:{$user->id}";
                
                Redis::pipeline(function ($pipe) use ($redisKey, $imageIds) {
                    $time = time();
                    foreach ($imageIds as $id) {
                        $pipe->zadd($redisKey, $time, $id);
                    }
                    // Retain only the most recent 2000 items (Memory Cap)
                    $pipe->zremrangebyrank($redisKey, 0, -2001);
                    // Expire in 14 days (1209600 seconds)
                    $pipe->expire($redisKey, 1209600);
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not record seen images for user {$user->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'images'               => $feed,
                'liked_image_ids'      => $likedImageIds,
                'bookmarked_image_ids' => $bookmarkedImageIds,
            ],
        ]);
    }

    /**
     * Toggle a Like on an image.
     */
    public function like(Image $image): JsonResponse
    {
        $user   = Auth::user();
        $result = $this->engine->toggleLike($user, $image);

        if ($result['action'] === 'like' && $image->user_id !== $user->id) {
            $image->user->notify(new ImageSocialNotification($user, $image, 'like'));
        }

        $image->loadCount('likes');

        return response()->json([
            'success'     => true,
            'action'      => $result['action'],
            'liked'       => $result['action'] === 'like',
            'likes_count' => $image->likes_count,
            'message'     => $result['action'] === 'like' ? 'تم الإعجاب بالصورة' : 'تم إزالة الإعجاب',
        ]);
    }

    /**
     * Toggle a Bookmark on an image.
     */
    public function bookmark(Image $image): JsonResponse
    {
        $user = Auth::user();

        if ($user->bookmarks()->where('image_id', $image->id)->exists()) {
            $user->bookmarks()->where('image_id', $image->id)->delete();
            $action  = 'unbookmark';
            $message = 'تم إزالة الصورة من المحفوظات';
        } else {
            $user->bookmarks()->create(['image_id' => $image->id]);
            $action  = 'bookmark';
            $message = 'تم حفظ الصورة بنجاح';
        }

        if ($action === 'bookmark' && $image->user_id !== $user->id) {
            $image->user->notify(new ImageSocialNotification($user, $image, 'bookmark'));
        }

        $image->loadCount('bookmarks');

        return response()->json([
            'success'     => true,
            'action'      => $action,
            'saved'       => $action === 'bookmark',
            'saves_count' => $image->bookmarks_count,
            'message'     => $message,
        ]);
    }

    /**
     * Implicit Feedback: Track when a user views an image.
     */
    public function trackView(Image $image): JsonResponse
    {
        $user = Auth::user();
        if ($user && $user->id !== $image->user_id) {
            $debounceKey = "viewed:{$user->id}:{$image->id}";
            try {
                if (!Redis::exists($debounceKey)) {
                    Redis::setex($debounceKey, 3600, 1);
                    UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.1);
                }
            } catch (\Exception $e) {
                // If Redis is down, we just skip debouncing and dispatch the job directly
                UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.1);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Implicit Feedback: Track when a user dwells on an image (>3 seconds).
     */
    public function trackDwell(Image $image): JsonResponse
    {
        $user = Auth::user();
        if ($user && $user->id !== $image->user_id) {
            $debounceKey = "dwelled:{$user->id}:{$image->id}";
            try {
                if (!Redis::exists($debounceKey)) {
                    Redis::setex($debounceKey, 43200, 1);
                    UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.5);
                }
            } catch (\Exception $e) {
                // If Redis is down, we just skip debouncing and dispatch the job directly
                UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.5);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Negative Signals: Heavily penalize image tags when user is not interested.
     */
    public function notInterested(Image $image): JsonResponse
    {
        $user = Auth::user();
        if ($user) {
            UpdateUserPreferencesJob::dispatch($user->id, $image->id, -5.0);

            UserHiddenImage::firstOrCreate([
                'user_id'  => $user->id,
                'image_id' => $image->id,
            ]);

            $redisKey = "hidden_images:{$user->id}";
            try {
                Redis::sadd($redisKey, $image->id);
                Redis::expire($redisKey, 90 * 24 * 60 * 60);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Redis is down while hiding image {$image->id} for user {$user->id}");
            }

            $this->engine->invalidateUserFeedCache($user->id);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.less_similar_images'),
        ]);
    }
}
