<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\Security\SecureShieldService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateProtectedImageJob implements ShouldQueue
{
    use Queueable;

    public $image;
    public $settings;

    /**
     * Create a new job instance.
     */
    public function __construct(Image $image, array $settings)
    {
        $this->image = $image;
        $this->settings = $settings;
    }

    /**
     * Execute the job.
     */
    public function handle(SecureShieldService $secureShield): void
    {
        Log::info("GenerateProtectedImageJob: Starting protection for image {$this->image->id}");

        try {
            $secureShield->protect($this->image, $this->settings);
            Log::info("GenerateProtectedImageJob: Successfully protected image {$this->image->id}");
        } catch (\Exception $e) {
            Log::error("GenerateProtectedImageJob: Failed to protect image {$this->image->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
