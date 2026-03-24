<?php

namespace App\Console\Commands;

use App\Models\UserPreference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DecayUserPreferences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preferences:decay {--factor=0.9 : The decay multiplier} {--threshold=0.5 : Minimum weight to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decay user preference weights over time to favor recent interactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $factor = (float) $this->option('factor');
        $threshold = (float) $this->option('threshold');

        $this->info("Starting preference decay... Factor: {$factor}, Threshold: {$threshold}");

        $processed = 0;
        $pruned = 0;

        UserPreference::chunk(500, function ($preferences) use ($factor, $threshold, &$processed, &$pruned) {
            foreach ($preferences as $preference) {
                $tagWeights = is_string($preference->tag_weights) ? json_decode($preference->tag_weights, true) : ($preference->tag_weights ?? []);
                $creatorWeights = is_string($preference->creator_weights) ? json_decode($preference->creator_weights, true) : ($preference->creator_weights ?? []);

                $updated = false;

                // Decay Tag Weights
                foreach ($tagWeights as $tag => $weight) {
                    $newWeight = $weight * $factor;
                    if ($newWeight < $threshold) {
                        unset($tagWeights[$tag]);
                        $pruned++;
                    } else {
                        $tagWeights[$tag] = $newWeight;
                    }
                    $updated = true;
                }

                // Decay Creator Weights
                foreach ($creatorWeights as $creator => $weight) {
                    $newWeight = $weight * $factor;
                    if ($newWeight < $threshold) {
                        unset($creatorWeights[$creator]);
                        $pruned++;
                    } else {
                        $creatorWeights[$creator] = $newWeight;
                    }
                    $updated = true;
                }

                if ($updated) {
                    $preference->update([
                        'tag_weights' => $tagWeights,
                        'creator_weights' => $creatorWeights,
                    ]);
                    $processed++;
                }
            }
        });

        $this->info("Decay complete. Processed {$processed} users, pruned {$pruned} irrelevant weights.");
        Log::channel('datadog')->info("User preferences decayed.", [
            'processed_users' => $processed,
            'pruned_weights' => $pruned,
            'factor' => $factor
        ]);
        
        return Command::SUCCESS;
    }
}
