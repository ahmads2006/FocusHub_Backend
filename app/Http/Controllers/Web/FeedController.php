<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\AI\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\ImageSocialNotification;

class FeedController extends Controller
{
    protected RecommendationEngine $engine;

    public function __construct(RecommendationEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Show the For You Feed page
     */
    public function index()
    {
        return view('feed.for_you');
    }

    /**
     * Get the highly personalized "For You" feed.
     */
    public function forYou(Request $request)
    {
        $limit = $request->get('limit', 20);
        $user = Auth::user();

        // Pass to the engine which handles caching, ZSET cold starts, and JSON matching
        $feed = $this->engine->getForYouFeed($user, $limit);

        return response()->json([
            'success' => true,
            'data' => $feed
        ]);
    }

    /**
     * Toggles a 'Like' on an image. (Rate limited by 'throttle:likes' middleware)
     */
    public function like(Request $request, Image $image)
    {
        $user = Auth::user();

        // The engine toggles the like, updates the ZSET trending scores, 
        // and dispatches the background Job for JSON preference weights.
        $result = $this->engine->toggleLike($user, $image);

        if ($result['action'] === 'like' && $image->user_id !== $user->id) {
            $image->user->notify(new ImageSocialNotification($user, $image, 'like'));
        }

        return response()->json([
            'success' => true,
            'action' => $result['action'],
            'message' => $result['action'] === 'like' ? 'تم الإعجاب بالصورة' : 'تم إزالة الإعجاب',
        ]);
    }

    /**
     * Toggles a 'Bookmark / Save' on an image. (Rate limited by 'throttle:likes' middleware)
     */
    public function bookmark(Request $request, Image $image)
    {
        $user = Auth::user();

        if ($user->bookmarks()->where('image_id', $image->id)->exists()) {
            $user->bookmarks()->where('image_id', $image->id)->delete();
            $action = 'unbookmark';
            $message = 'تم إزالة الصورة من المحفوظات';
        } else {
            $user->bookmarks()->create(['image_id' => $image->id]);
            $action = 'bookmark';
            $message = 'تم حفظ الصورة بنجاح';
        }

        if ($action === 'bookmark' && $image->user_id !== $user->id) {
            $image->user->notify(new ImageSocialNotification($user, $image, 'bookmark'));
        }

        return response()->json([
            'success' => true,
            'action' => $action,
            'message' => $message,
        ]);
    }

    /**
     * Implicit Feedback: Track when a user views an image.
     */
    public function trackView(Request $request, Image $image)
    {
        $user = Auth::user();
        if ($user && $user->id !== $image->user_id) {
            $debounceKey = "viewed:{$user->id}:{$image->id}";

            // Debounce: Only track view if not viewed in the last hour
            if (!\Illuminate\Support\Facades\Redis::exists($debounceKey)) {
                // Set expiry for 1 hour (3600 seconds)
                \Illuminate\Support\Facades\Redis::setex($debounceKey, 3600, 1);
                
                // Dispatch with a fractional weight (0.1) for a simple view
                \App\Jobs\UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.1);
            }
        }

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Implicit Feedback: Track when a user dwells (deeply views) an image for > 3 seconds.
     */
    public function trackDwell(Request $request, Image $image)
    {
        $user = Auth::user();
        if ($user && $user->id !== $image->user_id) {
            $debounceKey = "dwelled:{$user->id}:{$image->id}";

            // Debounce: Only track dwell if not dwelled in the last 12 hours
            if (!\Illuminate\Support\Facades\Redis::exists($debounceKey)) {
                // Set expiry for 12 hours
                \Illuminate\Support\Facades\Redis::setex($debounceKey, 43200, 1);
                
                // Dispatch with a much higher fractional weight (0.5) for a deep view
                \App\Jobs\UpdateUserPreferencesJob::dispatch($user->id, $image->id, 0.5);
            }
        }

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * Negative Signals: heavily penalize image tags when a user is not interested.
     */
    public function notInterested(Request $request, Image $image)
    {
        $user = Auth::user();
        if ($user) {
            // Heavy negative weight
            \App\Jobs\UpdateUserPreferencesJob::dispatch($user->id, $image->id, -5.0);
            
            // Add to DB for permanent persistence
            \App\Models\UserHiddenImage::firstOrCreate([
                'user_id' => $user->id,
                'image_id' => $image->id,
            ]);

            // Add to Redis for fast in-memory filtering (with 90 days TTL)
            $redisKey = "hidden_images:{$user->id}";
            \Illuminate\Support\Facades\Redis::sadd($redisKey, $image->id);
            \Illuminate\Support\Facades\Redis::expire($redisKey, 90 * 24 * 60 * 60); // 90 days

            // Instantly invalidate feed cache
            $this->engine->invalidateUserFeedCache($user->id);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.less_similar_images')
        ]);
    }

}

