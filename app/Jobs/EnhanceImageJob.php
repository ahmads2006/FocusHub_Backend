<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\AI\ImageEnhancementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * EnhanceImageJob
 *
 * Dispatched after AI analysis when qualityGrade is below high_quality.
 * Generates an enhanced CDN URL via ImageEnhancementService.
 */
class EnhanceImageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 10;

    public function __construct(
        public string $imageId
    ) {}

    public function handle(ImageEnhancementService $enhancer): void
    {
        $image = Image::with(['storage', 'aiMetadata'])->find($this->imageId);

        if (!$image) {
            Log::warning("EnhanceImageJob: Image {$this->imageId} not found, skipping.");
            return;
        }

        $qualityGrade = $image->aiMetadata->quality_grade
            ?? $image->quality_grade
            ?? 'high_quality';

        if ($qualityGrade === 'high_quality') {
            Log::debug("EnhanceImageJob: Image {$this->imageId} is already high_quality, skipping.");
            return;
        }

        Log::info("EnhanceImageJob: Enhancing image {$this->imageId} (grade: {$qualityGrade}).");

        $enhancedUrl = $enhancer->enhance($image);

        if ($enhancedUrl) {
            Log::info("EnhanceImageJob: Enhancement URL generated for image {$this->imageId}.");
        } else {
            Log::info("EnhanceImageJob: No CDN available to enhance image {$this->imageId}.");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("EnhanceImageJob: Failed for image {$this->imageId}: " . $exception->getMessage());
    }
}
