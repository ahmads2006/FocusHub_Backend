<?php

namespace App\Observers;

use App\Models\Like;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class LikeObserver
{
    public function created(Like $like)
    {
        try {
            // Increment the like count for the image owner
            $ownerId = $like->image->user_id;
            Redis::incr("user:{$ownerId}:stats:likes");
        } catch (\Throwable $e) {
            Log::warning('LikeObserver Redis incr failed', ['error' => $e->getMessage()]);
        }
    }

    public function deleted(Like $like)
    {
        try {
            // Decrement the like count for the image owner
            $ownerId = $like->image->user_id;
            Redis::decr("user:{$ownerId}:stats:likes");
        } catch (\Throwable $e) {
            Log::warning('LikeObserver Redis decr failed', ['error' => $e->getMessage()]);
        }
    }
}
