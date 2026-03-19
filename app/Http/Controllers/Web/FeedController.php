<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\AI\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return response()->json([
            'success' => true,
            'action' => $result['action'],
            'message' => $result['action'] === 'like' ? 'تم الإعجاب بالصورة' : 'تم إزالة الإعجاب',
        ]);
    }
}
