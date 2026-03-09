<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SharedLink;
use Illuminate\Support\Str;

class RotateShareTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'share-links:rotate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically rotate generated tokens for shared links every 5 hours.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Calculate the threshold time (5 hours ago)
        $thresholdTimestamp = now()->subHours(5);
        
        $this->info("Starting token rotation for links last updated before: {$thresholdTimestamp}");
        
        $rotatedCount = 0;

        // Use chunk to process large database tables efficiently to avoid memory exhaustion
        SharedLink::where('updated_at', '<=', $thresholdTimestamp)->chunkById(100, function ($links) use (&$rotatedCount) {
            foreach ($links as $link) {
                // Generate a brand new cryptographically secure token
                $newToken = $this->generateUniqueToken();
                
                // Update the token
                $link->update([
                    'token' => $newToken
                ]);
                
                // Updating via Eloquent model automatically modifies 'updated_at' 
                // effectively resetting its 5-hour clock.
                $rotatedCount++;
            }
        });

        $this->info("Completed token rotation for {$rotatedCount} links.");
        return Command::SUCCESS;
    }

    /**
     * Generate a completely unique token securely.
     *
     * @return string
     */
    protected function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (SharedLink::where('token', $token)->exists());

        return $token;
    }
}
