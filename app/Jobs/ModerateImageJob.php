<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Services\AI\ContentSafetyService;
use Exception;

class ModerateImageJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    protected $imagePath;
    protected $jobId;
    protected $albumId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imagePath, string $jobId, string $albumId, string $userId)
    {
        $this->imagePath = $imagePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(ContentSafetyService $safetyService): void
    {
        $absolutePath = Storage::disk('local')->path($this->imagePath);

        // Check file exists
        if (!file_exists($absolutePath)) {
            $this->markAsFailed();
            return;
        }

        // Mock an UploadedFile for ContentSafetyService compatibility
        $uploadedFile = new UploadedFile(
            $absolutePath,
            basename($absolutePath),
            mime_content_type($absolutePath) ?: 'image/jpeg',
            null,
            true // test mode to bypass move_uploaded_file check
        );

        try {
            $result = $safetyService->validate($uploadedFile);
            $status = $result['status'] ?? 'rejected';
            $isSensitive = $result['is_sensitive'] ?? false;
            $reason = $result['reason'] ?? null;
            $driver = $result['driver'] ?? 'unknown';

            // ✅ FIX: Always dispatch ProcessImageJob for ALL statuses (approved, pending_review, rejected).
            ProcessImageJob::dispatch($this->imagePath, $this->jobId, $this->albumId, $this->userId, $status, $isSensitive, $reason, $driver);

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error("ModerateImageJob failed: " . $e->getMessage());
            $this->markAsFailed();
        }
    }

    protected function markAsFailed()
    {
        $redisKey = 'opticvault:upload_progress:' . $this->jobId;
        $data = json_decode(Redis::get($redisKey), true);
        if ($data) {
            $data['failed_items']++;
            $this->checkIfCompleted($data, $redisKey);
        }
    }

    protected function checkIfCompleted($data, $redisKey)
    {
        $done = ($data['processed_items'] ?? 0)
              + ($data['rejected_items'] ?? 0)
              + ($data['failed_items'] ?? 0);

        if ($done >= ($data['total_items'] ?? 0)) {
            $data['status'] = 'completed';
            Storage::disk('local')->deleteDirectory('quarantine/extracted_' . $this->jobId);
        }
        Redis::set($redisKey, json_encode($data), 'EX', 86400);
    }
}
