<?php

namespace App\Observers;

use App\Models\Like;
use Illuminate\Support\Facades\Redis;

class LikeObserver
{
    public function created(Like $like)
    {
        // Increment the like count for the image owner
        $ownerId = $like->image->user_id;
        Redis::incr("user:{$ownerId}:stats:likes");
    }

    public function deleted(Like $like)
    {
        // Decrement the like count for the image owner
        $ownerId = $like->image->user_id;
        Redis::decr("user:{$ownerId}:stats:likes");
    }
}
