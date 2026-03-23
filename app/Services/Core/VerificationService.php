<?php

namespace App\Services\Core;

use App\Models\User;
use App\Models\Image;

class VerificationService
{
    /**
     * Check if a user meets the 100 green shots criteria.
     * green_zone (Approved) with 0.0 gore/nudity scores.
     */
    public function checkEligibility(User $user): void
    {
        $count = $user->images()
            ->whereHas('moderation', function ($q) {
                $q->where('status', 'approved');
            })
            ->whereHas('aiMetadata', function ($q) {
                // Assuming aiMetadata contains gore/nudity scores as JSON structure
                // Sightengine analyzer maps gore => 0.0
                $q->where('driver_name', 'sightengine')
                  ->whereJsonContains('data->goreScore', 0)
                  ->whereJsonContains('data->nudityScore', 0);
            })
            ->count();

        $verification = $user->verification;

        if ($count >= 100) {
            if (!$verification->is_verified) {
                $verification->update(['is_verified' => true]);
            }
        }
    }

    /**
     * Handle actions when a user uploads a "Red Zone" image.
     */
    public function handleRejectedImage(?Image $image, string $reason = ''): void
    {
        if (!$image) {
            return;
        }

        // If image was rejected due to gore or nudity (Red Zone)
        if (str_contains(strtolower($reason), 'gore') || str_contains(strtolower($reason), 'nudity') || str_contains(strtolower($reason), 'adult')) {
            $user = $image->user;
            
            // Revoke verification or flag for manual review
            if ($user->verification && $user->verification->is_verified) {
                $user->verification->update(['is_verified' => false]);
                
                // Optionally flag user.userStatus for review
                if ($user->userStatus) {
                    $user->userStatus->update(['is_shadow_hidden' => true]); 
                }
            }
        }
    }
}
