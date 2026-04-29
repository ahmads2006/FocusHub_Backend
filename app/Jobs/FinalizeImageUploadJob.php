<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\Core\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FinalizeImageUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageId;
    protected $tempPath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imageId, string $tempPath)
    {
        $this->imageId = $imageId;
        $this->tempPath = $tempPath;
    }

    /**
     * Execute the job.
     */
    public function handle(ImageService $imageService): void
    {
        // Bypass global visibility scope to find pending records
        $image = Image::withoutGlobalScopes()->find($this->imageId);
        
        if (!$image) {
            Log::error("FinalizeImageUploadJob: Image not found {$this->imageId}");
            return;
        }

        try {
            // 1. Download from temporary S3/Local storage to a local worker file
            $localTempFile = storage_path('app/processing_' . uniqid() . '_' . basename($this->tempPath));
            
            // Determine which disk to pull from
            $tempDisk = str_contains($this->tempPath, 'temp_uploads') ? 's3' : 'local';
            
            if (!Storage::disk($tempDisk)->exists($this->tempPath)) {
                Log::error("FinalizeImageUploadJob: Temp file missing {$this->tempPath}");
                return;
            }

            file_put_contents($localTempFile, Storage::disk($tempDisk)->get($this->tempPath));

            // 2. Run the heavy pipeline (Moderation, Sanitization, Final S3 Upload)
            $imageService->finalizeAsyncUpload($image, $localTempFile);

            // 3. Cleanup temp files
            if (file_exists($localTempFile)) {
                @unlink($localTempFile);
            }
            Storage::disk($tempDisk)->delete($this->tempPath);

        } catch (\Exception $e) {
            Log::error("FinalizeImageUploadJob Failed: " . $e->getMessage(), [
                'image_id' => $this->imageId,
                'temp_path' => $this->tempPath
            ]);
            
            // Mark as failed/review if needed
            $image->moderation()->update(['status' => 'pending_review']);
        }
    }
}
