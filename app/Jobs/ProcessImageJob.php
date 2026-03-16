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
    protected $isSensitive;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imagePath, string $jobId, string $albumId, string $userId, string $status, bool $isSensitive)
    {
        $this->imagePath = $imagePath;
        $this->jobId = $jobId;
        $this->albumId = $albumId;
        $this->userId = $userId;
        $this->status = $status;
        $this->isSensitive = $isSensitive;
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

            // Add simple watermark
            $img->text('OpticVault', $img->width() / 2, $img->height() / 2, function($font) use ($img) {
                $font->color([255, 255, 255, 0.4]);
                $font->align('center');
                $font->valign('middle');
                $font->size(max(24, intval($img->width() / 20))); // dynamic size based on width
            });

            // 1. Save Clean Original for dual-storage in storage/app/secure_uploads
            $cleanDir = 'secure_uploads/' . date('Y/m');
            Storage::disk('local')->makeDirectory($cleanDir);
            $uid = Str::uuid()->toString();
            $cleanPath = $cleanDir . '/' . $uid . '_' . $filename;
            Storage::disk('local')->put($cleanPath, file_get_contents($absolutePath));

            // 2. Save Public Preview/Watermarked
            $publicDir = 'images/' . date('Y/m');
            Storage::disk('public')->makeDirectory($publicDir);
            $publicPath = $publicDir . '/' . $uid . '_' . $filename;
            $img->save(Storage::disk('public')->path($publicPath), quality: 80);

            // 3. Save DB Record referencing the Album and saving Paths
            $imageDb = Image::create([
                'album_id' => $this->albumId,
                'user_id' => $this->userId,
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'filename' => $filename,
                'file_type' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                'size' => filesize($absolutePath),
                'privacy' => 'private',
            ]);

            $imageDb->storage()->updateOrCreate(['image_id' => $imageDb->id], [
                'original_path' => $cleanPath,
                'path' => $publicPath, // public URL accessible location
                'md5_hash' => md5_file($absolutePath),
            ]);

            $imageDb->moderation()->updateOrCreate(['image_id' => $imageDb->id], [
                'status' => $this->status, // 'approved', 'rejected', 'pending_review'
                'is_sensitive' => $this->isSensitive,
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
            $this->markAsFailed();
        }
    }

    protected function markAsProcessed()
    {
        $redisKey = 'opticvault:upload_progress:' . $this->jobId;
        $data = json_decode(Redis::get($redisKey), true);
        if ($data) {
            $data['processed_items']++;
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
