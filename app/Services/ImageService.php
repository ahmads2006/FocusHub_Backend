<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ImageKit\ImageKit;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageService
{
    protected $imageKit;
    protected $manager;

    public function __construct()
    {
        $this->imageKit = new ImageKit(
            config('services.imagekit.public_key') ?? '',
            config('services.imagekit.private_key') ?? '',
            config('services.imagekit.url_endpoint') ?? ''
        );

        // Using GD for maximum compatibility
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * The Full-Stack "Extract -> Sanitize -> Upload" Pipeline
     */
    public function processAndUpload(UploadedFile $file, array $data, string $userId): Image
    {
        try {
            // 1. Local Pre-Processing: Extract Technical EXIF
            $specs = $this->extractSpecs($file);

            // 2. Privacy & Sanitization: Strip GPS locally
            $cleanFile = $this->sanitizeLocally($file);

            // 3. Cloud Integration: Upload to ImageKit
            $cloudResponse = $this->uploadToCloud($cleanFile, $file->getClientOriginalName());

            // 4. Save to Database
            $image = Image::create([
                'user_id' => $userId,
                'album_id' => $data['album_id'] ?? null,
                'title' => $data['title'] ?? $file->getClientOriginalName(),
                'filename' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'path' => $cloudResponse->filePath,
                'imagekit_file_id' => $cloudResponse->fileId,
                'imagekit_file_path' => $cloudResponse->filePath,
                'technical_specs' => $specs,
                'size' => $file->getSize(),
                'privacy' => $data['privacy'] ?? 'public',
            ]);

            // 5. Cleanup local temporal file
            if (file_exists($cleanFile)) {
                @unlink($cleanFile);
            }

            return $image;

        } catch (\Exception $e) {
            Log::error("OpticVault Upload Failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate Optimized CDN URL with dynamic resizing
     */
    public function getDynamicUrl(Image $image, int $width = null): string
    {
        if (!$image->imagekit_file_path) {
            return asset($image->path);
        }

        $options = [
            'path' => $image->imagekit_file_path,
            'transformation' => [
                [
                    'format' => 'auto',
                    'quality' => 'auto',
                ]
            ]
        ];

        if ($width) {
            $options['transformation'][0]['width'] = $width;
        }

        return $this->imageKit->url($options);
    }

    /**
     * Local Sanitization: Strip GPS and personal metadata
     */
    protected function sanitizeLocally(UploadedFile $file): string
    {
        $img = $this->manager->read($file->getRealPath());
        
        // Save as temporary clean version (re-encoding strips original headers)
        $tempPath = storage_path('app/temp_' . uniqid() . '.jpg');
        $img->save($tempPath, quality: 90);

        return $tempPath;
    }

    /**
     * Technical EXIF Extraction
     */
    protected function extractSpecs(UploadedFile $file): array
    {
        $specs = [
            'camera_model' => 'Unknown',
            'aperture' => 'N/A',
            'shutter_speed' => 'N/A',
            'iso' => 'N/A',
            'focal_length' => 'N/A',
        ];

        try {
            $exif = @exif_read_data($file->getRealPath());
            if ($exif) {
                $specs['camera_model'] = $exif['Model'] ?? 'Unknown';
                $specs['iso'] = $exif['ISOSpeedRatings'] ?? 'N/A';
                $specs['aperture'] = isset($exif['FNumber']) ? 'f/' . $exif['FNumber'] : 'N/A';
                $specs['shutter_speed'] = $exif['ExposureTime'] ?? 'N/A';
                $specs['focal_length'] = isset($exif['FocalLength']) ? $exif['FocalLength'] . 'mm' : 'N/A';
            }
        } catch (\Exception $e) {
            Log::warning("EXIF extraction skipped: " . $e->getMessage());
        }

        return $specs;
    }

    /**
     * SDK Cloud Upload
     */
    protected function uploadToCloud(string $filePath, string $originalName)
    {
        $upload = $this->imageKit->uploadFiles([
            'file' => base64_encode(file_get_contents($filePath)),
            'fileName' => $originalName,
            'useUniqueFileName' => true,
            'folder' => '/opticvault/uploads',
        ]);

        if (isset($upload->error)) {
            throw new \Exception("ImageKit Upload Error: " . $upload->error->message);
        }

        return $upload->result;
    }
}
