<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register Observers
        \App\Models\ImageModeration::observe(\App\Observers\ImageModerationObserver::class);
        \App\Models\Like::observe(\App\Observers\LikeObserver::class);
        \App\Models\Connection::observe(\App\Observers\ConnectionObserver::class);
        \App\Models\Image::observe(\App\Observers\ImageObserver::class);
    }
}
