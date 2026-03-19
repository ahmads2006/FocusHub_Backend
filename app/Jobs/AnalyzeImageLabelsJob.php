<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\AI\ImageIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AnalyzeImageLabelsJob implements ShouldQueue
{
    use Queueable;

    public string $imageId;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Wait 30 seconds between retries to avoid quota hammering.
     */
    public int $backoff = 30;

    public function __construct(string $imageId)
    {
        $this->imageId = $imageId;
    }

    /**
     * Execute the job.
     */
    public function handle(ImageIntelligenceService $intelligence): void
    {
        $image = Image::find($this->imageId);

        if (!$image) {
            Log::warning("AnalyzeImageLabelsJob: Image {$this->imageId} not found, skipping.");
            return;
        }

        // Skip if labels already exist (idempotent)
        if (!empty($image->labels)) {
            Log::debug("AnalyzeImageLabelsJob: Image {$this->imageId} already has labels, skipping.");
            return;
        }

        Log::info("AnalyzeImageLabelsJob: Starting AI analysis for image {$this->imageId}.");

        $intelligence->analyzeAndTag($image);

        Log::info("AnalyzeImageLabelsJob: Analysis complete for image {$this->imageId}.");
    }

    /**
     * If all drivers fail (AllAnalyzersFailedException), don't retry — log and move on.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("AnalyzeImageLabelsJob: All AI analyzers failed for image {$this->imageId}: " . $exception->getMessage());
    }
}
