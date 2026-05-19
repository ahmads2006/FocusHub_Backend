<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule maintenance for secure quarantine zone
Schedule::command('quarantine:prune')->daily();

// Execute every 5 minutes and rotate tokens if 5 hours have passed since last rotation
Schedule::command('share-links:rotate')->everyFiveMinutes();

// Decay user preference weights weekly to favor recent interactions
Schedule::command('preferences:decay')->weekly();

// Auto-close inactive support chats
Schedule::command('support:autoclose')->everyMinute();

// Prune old support messages weekly (30 days retention)
Schedule::command('support:prune --days=30')->weekly();

// Clean up temporary files and old quarantined files weekly (Sunday at 2:00 AM - Low Traffic)
Schedule::command('opticvault:clean-garbage')->weeklyOn(0, '02:00');

// 🔄 Auto-Rotate Expired Album Invitations Every Hour
Schedule::call(function () {
    $expired = \App\Models\AlbumInvitation::where('expires_at', '<', now())->get();
    foreach ($expired as $invitation) {
        $album = $invitation->album;
        if (!$album) {
            $invitation->delete();
            continue;
        }

        do {
            $newRandomPart = \Illuminate\Support\Str::random(8);
            $newCode = "opalshot_{$newRandomPart}_{$invitation->role}";
        } while (\App\Models\AlbumInvitation::where('code', $newCode)->exists());

        $album->invitations()->create([
            'code'       => $newCode,
            'role'       => $invitation->role,
            'max_uses'   => 1,
            'expires_at' => now()->addHour(),
        ]);

        $invitation->delete();
    }
})->hourly();

// Clean up users who registered but didn't verify their account within an hour
Schedule::command('users:cleanup-unverified')->hourly();
