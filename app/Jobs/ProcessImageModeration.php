<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\AI\ContentSafetyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ProcessImageModeration implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;
    public $tries = 3;

    protected string $imageId;
    protected string $jobId;
    protected string $storagePath;

    /**
     * Create a new job instance.
     *
     * @param string $imageId   The image DB record ID
     * @param string $jobId     The batch job tracking ID (for Redis progress)
     * @param string $storagePath  The S3/local path where the image was stored
     */
    public function __construct(string $imageId, string $jobId, string $storagePath)
    {
        $this->imageId = $imageId;
        $this->jobId = $jobId;
        $this->storagePath = $storagePath;
    }

    /**
     * Execute the job: Run AI moderation and update DB + Redis progress.
     */
    public function handle(ContentSafetyService $contentSafety): void
    {
        try {
            $image = Image::withoutGlobalScopes()->find($this->imageId);

            if (!$image) {
                Log::warning("ProcessImageModeration: Image {$this->imageId} not found.");
                $this->incrementProgress('failed');
                return;
            }

            // ── AI Safety Analysis ──────────────────────────
            $moderationResult = [
                'status' => Image::STATUS_APPROVED,
                'metadata' => [],
            ];

            if (config('services.content_safety.enabled', true)) {
                // Build an UploadedFile-compatible object from storage
                $disk = Storage::disk('s3');
                $tempPath = sys_get_temp_dir() . '/' . basename($this->storagePath);

                // Download from S3 to temp for analysis
                file_put_contents($tempPath, $disk->get($this->storagePath));

                $fakeFile = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    basename($this->storagePath),
                    mime_content_type($tempPath),
                    null,
                    true // test mode to skip is_uploaded_file check
                );

                $moderationResult = $contentSafety->validate($fakeFile);

                // Cleanup temp file
                @unlink($tempPath);
            }

            // ── Update Moderation Status ────────────────────
            $status = $moderationResult['status'] ?? Image::STATUS_APPROVED;
            $isSensitive = in_array($status, [Image::STATUS_PENDING, Image::STATUS_REJECTED]);
            $reason = $moderationResult['metadata']['sensitivity_reason'] ?? null;

            $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                'status' => $status,
                'is_sensitive' => $isSensitive,
                'is_visible' => ($status !== Image::STATUS_REJECTED),
                'sensitivity_reason' => $reason,
            ]);

            // Cache safety result in Redis
            $safetyCacheKey = "opticvault:safety:{$image->id}";
            Redis::setex($safetyCacheKey, 3600, json_encode([
                'status' => $status,
                'is_sensitive' => $isSensitive,
                'timestamp' => time(),
            ]));

            // Track progress
            $progressType = ($status === Image::STATUS_REJECTED) ? 'rejected' : 'processed';
            $this->incrementProgress($progressType);

            Log::info("ProcessImageModeration: Image {$this->imageId} moderated as {$status}.");
            
            // ── Dispatch AI Intelligence Job (Tags & Caption) ────────────
            if ($status === Image::STATUS_APPROVED) {
                \App\Jobs\AnalyzeImageLabelsJob::dispatch($this->imageId);
                Log::info("ProcessImageModeration: Dispatched AnalyzeImageLabelsJob for image {$this->imageId}.");
            }

        } catch (\Exception $e) {
            Log::error("ProcessImageModeration failed for image {$this->imageId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            $this->incrementProgress('failed');
        }
    }

    /**
     * Increment the Redis progress counter for this batch job.
     */
    protected function incrementProgress(string $type): void
    {
        $redisKey = 'opticvault:upload_progress:' . $this->jobId;
        $data = null;
        try {
            $data = json_decode(Redis::get($redisKey), true);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in ProcessImageModeration progress: " . $e->getMessage());
        }

        if (!$data) {
            return;
        }

        $field = match ($type) {
            'processed' => 'processed_items',
            'rejected' => 'rejected_items',
            'failed' => 'failed_items',
            default => 'processed_items',
        };

        $data[$field] = ($data[$field] ?? 0) + 1;

        // Check if all items are done
        $done = ($data['processed_items'] ?? 0)
              + ($data['rejected_items'] ?? 0)
              + ($data['failed_items'] ?? 0);

        if ($done >= ($data['total_items'] ?? 0)) {
            $data['status'] = 'completed';
        }

        try {
            Redis::set($redisKey, json_encode($data), 'EX', 86400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Redis failure in ProcessImageModeration set: " . $e->getMessage());
        }
    }
}
