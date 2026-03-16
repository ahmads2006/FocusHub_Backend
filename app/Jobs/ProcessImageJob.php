<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use App\Models\Image;
use Illuminate\Support\Str;
use Exception;

class ProcessImageJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    protected $imagePath;
    protected $jobId;
    protected $albumId;
    protected $userId;
    protected $status;
    protected $reason;
    protected $isSensitive;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imagePath, string $jobId, string $albumId, string $userId, string $status, bool $isSensitive, string $reason = null)
    {
        $this->imagePath = $imagePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
        $this->status = $status;
        $this->isSensitive = $isSensitive;
        $this->reason = $reason;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $absolutePath = Storage::disk('local')->path($this->imagePath);
            $filename = basename($absolutePath);
            
            $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver() : new GdDriver());
            $img = $manager->read($absolutePath);

            // Add simple watermark (Wrapped in try-catch to prevent technical failure if GD font is missing)
            try {
                $img->text('OpticVault', $img->width() / 2, $img->height() / 2, function($font) use ($img) {
                    $font->color('ffffff'); // Simple white
                    $font->align('center');
                    $font->valign('middle');
                    $font->size(max(24, intval($img->width() / 20))); 
                });
            } catch (Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Watermark failed for {$this->imagePath}, skipping: " . $e->getMessage());
            }

            // 1. Dual-Storage Strategy
            $cleanPath = null;
            $publicPath = null;
            $storagePath = '';
            
            if ($this->status === 'rejected') {
                // RED LOGIC: Secure Private Quarantine, No Cloud/Public Upload
                $uid = Str::uuid()->toString();
                $cleanPath = 'quarantine/' . $uid . '_' . $filename;
                Storage::disk('local')->put($cleanPath, file_get_contents($absolutePath));
                $storagePath = $cleanPath;
                // No public preview for rejected content
            } else {
                // NORMAL/YELLOW LOGIC: Save Clean Original in storage/app/secure_uploads
                $cleanDir = 'secure_uploads/' . date('Y/m');
                Storage::disk('local')->makeDirectory($cleanDir);
                $uid = Str::uuid()->toString();
                $cleanPath = $cleanDir . '/' . $uid . '_' . $filename;
                Storage::disk('local')->put($cleanPath, file_get_contents($absolutePath));

                // Save Public Preview (usually with watermark)
                $publicDir = 'images/' . date('Y/m');
                Storage::disk('public')->makeDirectory($publicDir);
                $publicPath = $publicDir . '/' . $uid . '_' . $filename;
                $img->save(Storage::disk('public')->path($publicPath), quality: 80);
                $storagePath = $publicPath;
            }

            // 3. Save DB Record
            $album = \App\Models\Album::find($this->albumId);
            $inheritedPrivacy = $album ? $album->privacy : 'private';

            $imageDb = Image::create([
                'album_id' => $this->albumId,
                'user_id' => $this->userId,
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'filename' => $filename,
                'file_type' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                'size' => filesize($absolutePath),
                'privacy' => ($this->status === 'rejected') ? 'private' : $inheritedPrivacy, 
            ]);

            $imageDb->storage()->updateOrCreate(['image_id' => $imageDb->id], [
                'original_path' => $cleanPath,
                'path' => $storagePath, 
                'md5_hash' => md5_file($absolutePath),
            ]);

            $imageDb->moderation()->updateOrCreate(['image_id' => $imageDb->id], [
                'status' => $this->status,
                'is_sensitive' => $this->isSensitive,
                'is_visible' => ($this->status !== 'rejected'),
                'sensitivity_reason' => $this->reason,
            ]);

            $imageDb->meta()->updateOrCreate(['image_id' => $imageDb->id], [
                'technical_specs' => [] // Stubbed for processing
            ]);

            // Dispatch other related processing such as thumbnails if the system logic allows
            // \App\Jobs\ProcessImageThumbnails::dispatch($imageDb);

            // Cleanup temp extracted file
            Storage::disk('local')->delete($this->imagePath);

            $this->markAsProcessed();
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error("ProcessImageJob Failed for {$this->imagePath}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            $this->markAsFailed();
        }
    }

    protected function markAsProcessed()
    {
        $redisKey = 'opticvault:upload_progress:' . $this->jobId;
        $data = json_decode(Redis::get($redisKey), true);
        if ($data) {
            if ($this->status === 'rejected') {
                $data['rejected_items'] = ($data['rejected_items'] ?? 0) + 1;
            } else {
                $data['processed_items']++;
            }
            $this->checkIfCompleted($data, $redisKey);
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
        if (($data['processed_items'] + $data['failed_items']) >= $data['total_items']) {
            $data['status'] = 'completed';
            Storage::disk('local')->deleteDirectory('quarantine/extracted_' . $this->jobId);
        }
        Redis::set($redisKey, json_encode($data), 'EX', 86400);
    }
}
