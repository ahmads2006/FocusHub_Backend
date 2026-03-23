<?php

namespace App\Observers;

use App\Models\ImageModeration;
use App\Services\Core\VerificationService;

class ImageModerationObserver
{
    protected $verificationService;

    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Handle the ImageModeration "updated" event.
     */
    public function updated(ImageModeration $moderation): void
    {
        if ($moderation->isDirty('status')) {
            // Bypass global scope 'visible' which might hide the image if status is rejected
            $image = $moderation->image()->withoutGlobalScopes()->first();

            if (!$image) {
                return;
            }

            if ($moderation->status === 'approved') {
                $this->verificationService->checkEligibility($image->user);
                
                // Analytics: increment if public
                if ($image->privacy === 'public') {
                    \Illuminate\Support\Facades\Redis::incr("user:{$image->user_id}:stats:photos");
                }
            } elseif ($moderation->status === 'rejected') {
                // Determine reason if available (e.g., sensitivity_reason)
                $reason = $moderation->sensitivity_reason ?? '';
                $this->verificationService->handleRejectedImage($image, $reason);
            }
            
            // Analytics: decrement if it was approved and public, but changed
            if ($moderation->getOriginal('status') === 'approved' && $moderation->status !== 'approved') {
                if ($image->privacy === 'public') {
                    \Illuminate\Support\Facades\Redis::decr("user:{$image->user_id}:stats:photos");
                }
            }
        }
    }
}
