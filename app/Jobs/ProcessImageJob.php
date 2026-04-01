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
use App\Notifications\ImageStatusNotification;
use App\Models\User;

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
    protected $driver;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imagePath, string $jobId, string $albumId, string $userId, string $status, bool $isSensitive, ?string $reason = null, string $driver = 'unknown')
    {
        $this->imagePath = $imagePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
        $this->status = $status;
        $this->isSensitive = $isSensitive;
        $this->reason = $reason;
        $this->driver = $driver;
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

            // Watermark is NOT applied here — it's handled at CDN delivery time
            // via ImageKit URL transformations (see ImageKitService::getWatermarkedUrl)

            // 1. Dual-Storage Strategy
            $cleanPath = null;
            $publicPath = null;
            $storagePath = '';
            
            if ($this->status === 'rejected') {
                // RED LOGIC: Secure Private Quarantine, No Cloud/Public Upload
                $year = date('Y');
                $month = date('m');
                $uid = Str::uuid()->toString();
                $cleanPath = "quarantine/{$year}/{$month}/{$this->userId}/" . $uid . '_' . $filename;
                Storage::disk('local')->put($cleanPath, file_get_contents($absolutePath));
                $storagePath = $cleanPath;
                // No public preview for rejected content
            } else {
                // NORMAL/YELLOW LOGIC: Save Clean Original in storage/app/secure_uploads
                $year = date('Y');
                $month = date('m');
                $uid = Str::uuid()->toString();

                $cleanPath = "secure_uploads/{$year}/{$month}/{$this->userId}/" . $uid . '_' . $filename;
                Storage::disk('local')->put($cleanPath, file_get_contents($absolutePath));

                // Save Public Preview (clean, watermark applied at CDN delivery time)
                $publicPath = "photos/{$year}/{$month}/{$this->userId}/" . $uid . '_' . $filename;
                
                // Ensure directory exists
                Storage::disk('public')->makeDirectory(dirname($publicPath));
                
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
                'path' => $storagePath, // v15.0 Sync Full Path
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

            // STAGE 3: Store safety result in Redis Pipeline Cache
            $safetyCacheKey = "opticvault:safety:{$imageDb->id}";
            Redis::setex($safetyCacheKey, 3600, json_encode([
                'status' => $this->status,
                'is_sensitive' => $this->isSensitive,
                'timestamp' => time()
            ]));

            $imageDb->meta()->updateOrCreate(['image_id' => $imageDb->id], [
                'technical_specs' => [] // Stubbed for processing
            ]);

            // Save AI Metadata
            $imageDb->aiMetadata()->create([
                'driver_name' => $this->driver,
                'is_sensitive' => $this->isSensitive,
                'extracted_tags' => explode(',', $this->reason),
            ]);

            // Dispatch other related processing such as thumbnails if the system logic allows
            // \App\Jobs\ProcessImageThumbnails::dispatch($imageDb);

            // Cleanup temp extracted file
            Storage::disk('local')->delete($this->imagePath);

            // Trigger Success Notification if it was rejected or just processed
            $u = User::find($this->userId);
            if ($u) {
                $statusMsg = ($this->status === 'rejected' ? "تم رفض صورتك بسبب مخالفة المعايير: {$this->reason}" : "تمت معالجة صورتك بنجاح وقبولها.");
                $u->notify(new ImageStatusNotification($imageDb, $this->status, $statusMsg));
            }

            $this->markAsProcessed();
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error("ProcessImageJob Failed for {$this->imagePath}: " . $e->getMessage(), [
                'exception' => $e
            ]);
            
            // Trigger Failed Notification
            $u = User::find($this->userId);
            if ($u) {
                // We don't have $imageDb here if it failed before creation
                // But we can still send a generic message or try to find it
                // For now, let's just log it if it failed early
            }

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
