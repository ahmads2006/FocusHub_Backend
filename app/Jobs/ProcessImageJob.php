<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
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
    protected $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imagePath, string $jobId, string $albumId, string $userId, string $status, bool $isSensitive, ?string $reason = null, string $driver = 'unknown', array $metadata = [])
    {
        $this->imagePath = $imagePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
        $this->status = $status;
        $this->isSensitive = $isSensitive;
        $this->reason = $reason;
        $this->driver = $driver;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $absolutePath = Storage::disk('local')->path($this->imagePath);
            $filename = basename($absolutePath);
            
            // Watermark and resizing is NOT applied here — it's handled at CDN delivery time
            // via ImageKit URL transformations for zero backend CPU cost.

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
                
                // Pure Copy (0 CPU footprint compared to Intervention Re-encoding)
                Storage::disk('public')->put($publicPath, file_get_contents($absolutePath));
                $storagePath = $publicPath;
            }

            // 3. Save DB Record
            $album = \App\Models\Album::find($this->albumId);
            $inheritedPrivacy = $album ? $album->privacy : 'private';

            $imageDb = Image::create([
                'album_id' => $this->albumId,
                'user_id' => $this->userId,
                'title' => $this->metadata['title'] ?? pathinfo($filename, PATHINFO_FILENAME),
                'description' => $this->metadata['description'] ?? null,
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

            // Save settings/permissions
            $settingsDataToSave = [
                'allow_download'        => isset($this->metadata['allow_download']) ? filter_var($this->metadata['allow_download'], FILTER_VALIDATE_BOOLEAN) : true,
                'watermark_on_download' => isset($this->metadata['watermark_on_download']) ? filter_var($this->metadata['watermark_on_download'], FILTER_VALIDATE_BOOLEAN) : false,
            ];
            
            $wmFields = ['watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
            foreach ($wmFields as $f) {
                if (array_key_exists($f, $this->metadata ?? [])) {
                    $settingsDataToSave[$f] = $this->metadata[$f];
                }
            }
            
            $imageDb->settings()->updateOrCreate(['image_id' => $imageDb->id], $settingsDataToSave);

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
        try {
            $data = json_decode(Redis::get($redisKey), true);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in markAsProcessed: " . $e->getMessage());
            $data = null;
        }
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
        try {
            $data = json_decode(Redis::get($redisKey), true);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in markAsFailed: " . $e->getMessage());
            $data = null;
        }
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

            // Log activity completion
            $user = \App\Models\User::find($this->userId);
            $album = \App\Models\Album::find($this->albumId);
            if ($user && $album) {
                activity()
                    ->performedOn($album)
                    ->causedBy($user)
                    ->log("Uploaded {$data['processed_items']} images to album");
            }
        }
        try {
            Redis::set($redisKey, json_encode($data), 'EX', 86400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Redis failure in checkIfCompleted: " . $e->getMessage());
        }
    }
}
