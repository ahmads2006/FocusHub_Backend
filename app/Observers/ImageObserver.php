<?php

namespace App\Observers;

use App\Models\Image;
use Illuminate\Support\Facades\Redis;

class ImageObserver
{
    /**
     * Handle the Image "updated" event.
     */
    public function updated(Image $image): void
    {
        if ($image->isDirty('privacy')) {
            // Only affects count if it's currently approved
            if ($image->moderation && $image->moderation->status === 'approved') {
                if ($image->privacy === 'public') {
                    Redis::incr("user:{$image->user_id}:stats:photos");
                } elseif ($image->getOriginal('privacy') === 'public') {
                    Redis::decr("user:{$image->user_id}:stats:photos");
                }
            }
        }
    }

    /**
     * Handle the Image "deleted" event.
     */
    public function deleted(Image $image): void
    {
        if ($image->privacy === 'public' && $image->moderation && $image->moderation->status === 'approved') {
            Redis::decr("user:{$image->user_id}:stats:photos");
        }
    }
}
