<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Exception;

class ExtractArchiveJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600;

    protected $filePath;
    protected $jobId;
    protected $albumId;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, string $jobId, string $albumId, string $userId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $absolutePath = Storage::disk('local')->path($this->filePath);
        $extractPath = Storage::disk('local')->path('quarantine/extracted_' . $this->jobId);
        
        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $extractedSuccessfully = false;

        try {
            \Illuminate\Support\Facades\Log::info("Starting extraction for job {$this->jobId}. Extension: {$extension}");
            if ($extension === 'rar') {
                $result = \Illuminate\Support\Facades\Process::timeout(600)->run(['unrar', 'x', '-y', $absolutePath, $extractPath . '/']);
                $extractedSuccessfully = $result->successful();
                if (!$extractedSuccessfully) {
                    \Illuminate\Support\Facades\Log::error("Unrar failed for job {$this->jobId}: " . $result->errorOutput());
                }
            } elseif ($extension === '7z') {
                $result = \Illuminate\Support\Facades\Process::timeout(600)->run(['7z', 'x', $absolutePath, '-o' . $extractPath, '-y']);
                $extractedSuccessfully = $result->successful();
                if (!$extractedSuccessfully) {
                    \Illuminate\Support\Facades\Log::error("7z failed for job {$this->jobId}: " . $result->errorOutput());
                }
            } else {
                $zip = new \ZipArchive;
                if ($zip->open($absolutePath) === true) {
                    $zip->extractTo($extractPath);
                    $zip->close();
                    $extractedSuccessfully = true;
                } else {
                    \Illuminate\Support\Facades\Log::error("ZipArchive failed to open file for job {$this->jobId}");
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Archive extraction Exception for job {$this->jobId}: " . $e->getMessage());
        }

        if ($extractedSuccessfully) {
            \Illuminate\Support\Facades\Log::info("Extraction successful for job {$this->jobId}. Scanning for images...");

            // Find all common image extensions
            $files = Storage::disk('local')->allFiles('quarantine/extracted_' . $this->jobId);
            $validExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $imagePaths = [];

            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, $validExtensions)) {
                    $imagePaths[] = $file;
                }
            }

            $totalImages = count($imagePaths);
            $redisKey = 'opticvault:upload_progress:' . $this->jobId;

            if ($totalImages === 0) {
                // Done - nothing to process
                Redis::set($redisKey, json_encode([
                    'total_items' => 0,
                    'processed_items' => 0,
                    'failed_items' => 0,
                    'status' => 'completed'
                ]));
                $this->cleanup();
                return;
            }

            // Initialize progress for the images found
            Redis::set($redisKey, json_encode([
                'total_items' => $totalImages,
                'processed_items' => 0,
                'rejected_items' => 0,
                'failed_items' => 0,
                'status' => 'processing'
            ]), 'EX', 86400);

            // Dispatch Moderation job for each
            foreach ($imagePaths as $imagePath) {
                ModerateImageJob::dispatch($imagePath, $this->jobId, $this->albumId, $this->userId);
            }

            // Delete the zip file it self since it's extracted
            Storage::disk('local')->delete($this->filePath);
        } else {
            // Unzipping failed
            $redisKey = 'opticvault:upload_progress:' . $this->jobId;
            Redis::set($redisKey, json_encode([
                'total_items' => 0,
                'processed_items' => 0,
                'failed_items' => 1,
                'status' => 'failed',
                'error' => 'Archive could not be extracted.'
            ]), 'EX', 86400);
            Storage::disk('local')->delete($this->filePath);
        }
    }

    protected function cleanup()
    {
        Storage::disk('local')->delete($this->filePath);
        Storage::disk('local')->deleteDirectory('quarantine/extracted_' . $this->jobId);
    }
}
