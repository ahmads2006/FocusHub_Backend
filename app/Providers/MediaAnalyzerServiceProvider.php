<?php

namespace App\Providers;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\MediaAnalyzerManager;
use Illuminate\Support\ServiceProvider;

class MediaAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaAnalyzerManager::class, function ($app) {
            return new MediaAnalyzerManager($app);
        });

        $this->app->singleton(MediaAnalyzerInterface::class, function ($app) {
            return $app->make(MediaAnalyzerManager::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
