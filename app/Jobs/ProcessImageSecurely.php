<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImageSecurely implements ShouldQueue
{
    use Queueable;

    protected $image;
    protected $tempPath;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Image $image, string $tempPath, array $data = [])
    {
        $this->image = $image;
        $this->tempPath = $tempPath;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(
        \App\Services\MetadataService $metadataService,
        \App\Services\SecureShieldService $secureShield
    ): void {
        try {
            // 1. Extract Technical EXIF before sanitization
            // We use the file from quarantine/temp storage
            $tempFile = new \Illuminate\Http\File(storage_path('app/' . $this->tempPath));
            
            // We need a fake UploadedFile wrapper for MetadataService which expects it
            // or we refactor MetadataService to accept plain files. 
            // For now, let's just use the path as MetadataService uses getRealPath()
            $exifData = $metadataService->extractTechnicalFromPath($tempFile->getRealPath());

            // 2. Sanitize and Re-encode (Strips GPS and hidden blobs)
            // Use Intervention Image to create a clean visual-only copy
            $manager = new \Intervention\Image\ImageManager(
                extension_loaded('imagick') 
                    ? new \Intervention\Image\Drivers\Imagick\Driver() 
                    : new \Intervention\Image\Drivers\Gd\Driver()
            );

            $img = $manager->read($tempFile->getRealPath());
            
            // Re-encoding is the ultimate sanitizer
            $extension = pathinfo($this->image->filename, PATHINFO_EXTENSION);
            $finalFilename = $this->image->id . '_' . time() . '.' . $extension;
            $finalDirectory = 'images/' . date('Y/m');
            $finalPath = $finalDirectory . '/' . $finalFilename;

            // Ensure directory exists
            \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($finalDirectory);
            $absoluteFinalPath = \Illuminate\Support\Facades\Storage::disk('public')->path($finalPath);

            // Save clean copy (stripping all original metadata headers)
            $img->save($absoluteFinalPath, quality: 90);

            // 3. Update Database Records
            $this->image->update([
                'path' => $finalPath,
                'file_type' => $extension,
                'technical_specs' => array_intersect_key($exifData, array_flip([
                    'camera_make', 'camera_model', 'lens_type', 'focal_length', 'aperture', 'shutter_speed', 'iso'
                ])),
                'exif_data' => $exifData['extra_info'] ?? [],
                'size' => filesize($absoluteFinalPath),
            ]);

            // Save to dedicated metadata table
            \App\Models\ImageMetadata::updateOrCreate(
                ['image_id' => $this->image->id],
                $exifData
            );

            // 4. Cleanup quarantine
            \Illuminate\Support\Facades\Storage::delete($this->tempPath);

            // 5. Trigger further processing (Thumbnails) if needed
            \App\Jobs\ProcessImageThumbnails::dispatch($this->image);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Secure Processing Job Failed: " . $e->getMessage());
            // Optionally notify photographer of failure
            throw $e;
        }
    }
}
