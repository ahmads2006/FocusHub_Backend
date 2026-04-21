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

// Clean up temporary files and old quarantined files weekly (Sunday at 2:00 AM - Low Traffic)
Schedule::command('opticvault:clean-garbage')->weeklyOn(0, '02:00');
