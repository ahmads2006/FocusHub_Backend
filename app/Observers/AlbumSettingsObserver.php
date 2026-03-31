<?php

namespace App\Observers;

use App\Models\AlbumSettings;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class AlbumSettingsObserver
{
    /**
     * Handle the AlbumSettings "updated" event.
     */
    public function updated(AlbumSettings $settings): void
    {
        // Blurring system has been removed as per requirements.
        // Sensitivity is now handled purely via UI warnings.
    }
}
