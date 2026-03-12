<?php

namespace App\Services\Core;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ImageKit\ImageKit;
use App\Services\AI\ContentSafetyService;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ImageService
{
    protected $imageKit;
    protected $manager;
    protected $contentSafety;

    public function __construct(ContentSafetyService $contentSafety)
    {
        $this->contentSafety = $contentSafety;
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
            // 0. Rate Limiting (10 uploads per hour per user)
            $this->checkRateLimit($userId);

            // 0.1 Hybrid AI Safety System (Local Heuristics + Google Vision)
            $moderationResult = [
                'status' => Image::STATUS_APPROVED,
                'metadata' => []
            ];

            if (config('services.content_safety.enabled', true)) {
                $moderationResult = $this->contentSafety->validate($file);
                
                // CRITICAL: If rejected, block the upload immediately
                if ($moderationResult['status'] === 'rejected') {
                    throw ValidationException::withMessages([
                        'image' => [ $moderationResult['reason'] ?? 'هذه الصورة تخالف سياسات الموقع لمكافحة المحتوى غير اللائق، وتم حظرها فوراً.' ]
                    ]);
                }
            }

            // 1. Local Pre-Processing: Extract Technical EXIF
            $specs = $this->extractSpecs($file);

            // 2. Privacy & Sanitization: Strip GPS locally
            $cleanFile = $this->sanitizeLocally($file);

            // 3. Cloud Integration: Upload to ImageKit (with Graceful Local Fallback)
            $imagekitFileId = null;
            $imagekitFilePath = null;
            
            try {
                $cloudResponse = $this->uploadToCloud($cleanFile, $file->getClientOriginalName());
                $imagekitFileId = $cloudResponse->fileId;
                $imagekitFilePath = $cloudResponse->filePath;
                $path = $cloudResponse->filePath;
            } catch (\Exception $e) {
                Log::warning("OpticVault Cloud Fallback: " . $e->getMessage() . ". Using local storage.");
                
                $fileName = uniqid('fallback_') . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
                $relativePath = 'images/' . $fileName;
                
                // Save to public disk so asset('storage/images/...') works via symlink
                Storage::disk('public')->put($relativePath, file_get_contents($cleanFile));
                $path = $relativePath;
            }

            // 4. Save to Database
            $image = Image::create([
                'user_id' => $userId,
                'album_id' => $data['album_id'] ?? null,
                'title' => $data['title'] ?? 'OpticVault',
                'filename' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'path' => $path,
                'imagekit_file_id' => $imagekitFileId,
                'imagekit_file_path' => $imagekitFilePath,
                'technical_specs' => $specs,
                'size' => $file->getSize(),
                'privacy' => $data['privacy'] ?? 'public',
                'allow_download' => isset($data['allow_download']),
                'watermark_on_download' => isset($data['watermark_on_download']),
                'status' => $moderationResult['status'],
                'ai_metadata' => $moderationResult['metadata'],
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
     * Delete an image from cloud/local storage and database
     */
    public function delete(Image $image): void
    {
        try {
            // 1. Delete from ImageKit if exists
            if (!empty($image->imagekit_file_id)) {
                try {
                    $this->imageKit->deleteFile($image->imagekit_file_id);
                } catch (\Exception $e) {
                    Log::warning("OpticVault ImageKit Delete Failed for file_id {$image->imagekit_file_id}: " . $e->getMessage());
                }
            }

            // 2. Delete local fallback or thumbnails
            if (!empty($image->path) && Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }

            // 3. Delete from database
            $image->delete();

        } catch (\Exception $e) {
            Log::error("OpticVault Delete Failed: " . $e->getMessage());
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
     * Specialized logic for generating premium profile icons.
     * Force 150x150 square crop, WebP format, <20KB.
     */
    public function generateAvatar(UploadedFile $file): string
    {
        $filename = 'avatar_' . uniqid() . '.webp';
        $directory = 'avatars';
        $path = $directory . '/' . $filename;

        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $img = $this->manager->read($file->getRealPath());

        // Smart Cropping: Cover a 150x150 area (Centered)
        $img->cover(150, 150, 'center');

        // Save as WebP with high compression
        $encoded = $img->toWebp(70);
        
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    protected function uploadToCloud(string $filePath, string $originalName)
    {
        $upload = $this->imageKit->uploadFiles([
            'file' => base64_encode(file_get_contents($filePath)),
            'fileName' => $originalName,
            'useUniqueFileName' => true,
            'folder' => '/opticvault/uploads',
        ]);

        if (isset($upload->error) && $upload->error) {
            $errorMsg = is_string($upload->error) ? $upload->error : ($upload->error->message ?? json_encode($upload->error));
            throw new \Exception("ImageKit Upload Error: " . $errorMsg);
        }

        if (empty($upload->result)) {
            $statusCode = $upload->responseMetadata['statusCode'] ?? 'Unknown';
            throw new \Exception("ImageKit Upload Error: Empty response from ImageKit (HTTP {$statusCode})");
        }

        return $upload->result;
    }

    /**
     * Rate Limiting: 50 uploads per hour. Super Admins are exempt.
     */
    protected function checkRateLimit(string $userId): void
    {
        $user = \App\Models\User::find($userId);
        if ($user && ($user->role === 'super_admin' || $user->hasRole('super_admin'))) {
            return;
        }

        $executed = RateLimiter::attempt(
            'upload-limit:' . $userId,
            $maxAttempts = 50,
            function () {
                // Register attempt
            },
            $decaySeconds = 3600
        );

        if (!$executed) {
            throw ValidationException::withMessages([
                'image' => __('لقد تجاوزت الحد الأقصى للرفع (10 صور في الساعة). يرجى المحاولة لاحقاً.'),
            ]);
        }
    }
}
