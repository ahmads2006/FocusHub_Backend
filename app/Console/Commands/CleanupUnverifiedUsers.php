<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class CleanupUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:cleanup-unverified';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users who have not verified their account within 1 hour of registration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $oneHourAgo = Carbon::now()->subHour();

        // Find users created more than an hour ago who are still not verified
        $usersToDelete = User::where('created_at', '<=', $oneHourAgo)
            ->whereHas('verification', function ($query) {
                $query->where('is_verified', false);
            })
            ->get();

        $count = $usersToDelete->count();

        foreach ($usersToDelete as $user) {
            $user->delete();
        }

        $this->info("Deleted {$count} unverified user(s) successfully.");
    }
}
