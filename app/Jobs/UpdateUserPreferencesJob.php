<?php

namespace App\Jobs;

use App\Models\Image;
use App\Models\UserPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateUserPreferencesJob implements ShouldQueue
{
    use Queueable;

    public $userId;
    public $imageId;
    public $weightAdjustment;

    /**
     * Create a new job instance.
     */
    public function __construct(string $userId, string $imageId, float $weightAdjustment = 1.0)
    {
        $this->userId = $userId;
        $this->imageId = $imageId;
        $this->weightAdjustment = $weightAdjustment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $image = Image::find($this->imageId);
        if (!$image) return;

        DB::transaction(function () use ($image) {
            $preference = UserPreference::firstOrCreate(['user_id' => $this->userId]);

            $tagWeights = is_string($preference->tag_weights) ? json_decode($preference->tag_weights, true) : ($preference->tag_weights ?? []);
            $creatorWeights = is_string($preference->creator_weights) ? json_decode($preference->creator_weights, true) : ($preference->creator_weights ?? []);

            // 1. Update Creator Affinity
            $creatorId = $image->user_id;
            if (!isset($creatorWeights[$creatorId])) {
                $creatorWeights[$creatorId] = 0;
            }
            $creatorWeights[$creatorId] += $this->weightAdjustment;
            if ($creatorWeights[$creatorId] <= 0) unset($creatorWeights[$creatorId]);

            // 2. Update Tag/Label Affinity
            $labels = is_string($image->labels) ? json_decode($image->labels, true) : ($image->labels ?? []);
            
            // Labels are parsed from JSON column above

            foreach ($labels as $label) {
                // Determine label string (assuming standard Google Vision JSON structure e.g., ["description" => "Forest"])
                $tagName = null;
                if (is_string($label)) {
                    $tagName = $label;
                } elseif (is_array($label) && isset($label['description'])) {
                    $tagName = $label['description'];
                }

                if ($tagName) {
                    if (!isset($tagWeights[$tagName])) {
                        $tagWeights[$tagName] = 0;
                    }
                    $tagWeights[$tagName] += $this->weightAdjustment;
                    if ($tagWeights[$tagName] <= 0) unset($tagWeights[$tagName]);
                }
            }

            // 3. Save Preferences
            $preference->update([
                'tag_weights' => $tagWeights,
                'creator_weights' => $creatorWeights,
            ]);

            // Cache Invalidation Note:
            // We intentionally do NOT flush the feed cache here anymore.
            // Fast/heavy implicit actions (like View Tracking) will update the weights
            // silently without destroying the user's current feed cache. Explicit
            // actions (Like/Unlike) already flush the cache manually in the Controller.

            // Datadog Tracking happens via the Engine (Gauge), but we can log securely here.
            Log::channel('datadog')->debug('User preferences updated successfully via Job', [
                'user_id' => $this->userId,
                'image_id' => $this->imageId,
                'adjustment' => $this->weightAdjustment,
            ]);
        });
    }
}
