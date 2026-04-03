<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DecayUserPreferencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'preferences:decay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decay user preferences organically to allow new interests to surface.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting global preference time decay...");

        $preferences = \App\Models\UserPreference::cursor();
        $decayFactor = 0.85; // Remove 15% of the weight
        $prunedCount = 0;

        foreach ($preferences as $preference) {
            $tagWeights = is_string($preference->tag_weights) ? json_decode($preference->tag_weights, true) : ($preference->tag_weights ?? []);
            $creatorWeights = is_string($preference->creator_weights) ? json_decode($preference->creator_weights, true) : ($preference->creator_weights ?? []);

            $newTagWeights = [];
            foreach ($tagWeights as $tag => $weight) {
                $newWeight = $weight * $decayFactor;
                if ($newWeight > 0.1) {
                    $newTagWeights[$tag] = round($newWeight, 2);
                } else {
                    $prunedCount++;
                }
            }

            $newCreatorWeights = [];
            foreach ($creatorWeights as $creator => $weight) {
                $newWeight = $weight * $decayFactor;
                if ($newWeight > 0.1) {
                    $newCreatorWeights[$creator] = round($newWeight, 2);
                } else {
                    $prunedCount++;
                }
            }

            $preference->update([
                'tag_weights' => $newTagWeights,
                'creator_weights' => $newCreatorWeights,
            ]);
        }

        $this->info("Decay complete. Pruned $prunedCount dead affinities.");
    }
}
