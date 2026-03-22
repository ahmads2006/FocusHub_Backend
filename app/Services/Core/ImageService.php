<?php

namespace App\Services\Core;

use App\Models\Image;
use App\Jobs\AnalyzeImageLabelsJob;
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
            }

            // 1. Local Pre-Processing: Extract Technical EXIF
            $specs = $this->extractSpecs($file);

            // 2. Privacy & Sanitization: Strip GPS locally
            $cleanFile = $this->sanitizeLocally($file);

            // 2.1 Fetch Album context
            $album = null;
            if (isset($data['album_id'])) {
                $album = \App\Models\Album::find($data['album_id']);
            }
            $isPublicAlbum = !$album || $album->privacy === 'public';

            // 3. Cloud & Storage Routing
            $imagekitFileId = null;
            $imagekitFilePath = null;
            $path = '';
            $originalPath = null;

            if ($moderationResult['status'] === 'rejected') {
                if (!$isPublicAlbum) {
                    // BLOCKED: Red content in private/hidden albums is not allowed
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'image' => 'عذراً، لا يمكن رفع محتوى غير لائق في الألبومات الخاصة أو المشتركة. تم حجب العملية بالكامل.'
                    ]);
                }

                // RED LOGIC: Secure Private Quarantine, No Cloud Upload (Public context)
                $fileName = uniqid('rejected_') . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
                $relativePath = 'quarantine/' . $fileName;
                Storage::disk('local')->put($relativePath, file_get_contents($cleanFile));
                $path = $relativePath;
                $originalPath = $relativePath;
            } else {
                if ($moderationResult['is_sensitive'] ?? false) {
                    // YELLOW LOGIC: Dual-Storage Strategy
                    // Always save the clean original file securely (needed for privacy transitions)
                    $originalFileName = uniqid('original_') . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
                    $originalPath = 'secure_uploads/' . $originalFileName;
                    Storage::disk('local')->put($originalPath, file_get_contents($cleanFile));

                    if ($isPublicAlbum) {
                        // Server-side Blur & Pixelate for public preview
                        $img = $this->manager->read($cleanFile);
                        $img->blur(50)->pixelate(10);
                        $img->save($cleanFile, quality: 90);
                    }
                }

                // GREEN/YELLOW LOGIC: Upload to ImageKit (with Graceful Fallback)
                try {
                    $cloudResponse = $this->uploadToCloud($cleanFile, $file->getClientOriginalName());
                    $imagekitFileId = $cloudResponse->fileId;
                    $imagekitFilePath = $cloudResponse->filePath;
                    $path = $cloudResponse->filePath;

                    // ── Optional: Also push to LocalStack S3 for background Python scanner ──
                    // DISABLED: Pushing to LocalStack S3 synchronously blocks the PHP thread for 
                    // up to 3 minutes if the Docker container is unresponsive or bucket is missing.
                    /*
                    try {
                        $s3Key = 'images/' . date('Y/m') . '/' . uniqid('mirror_') . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
                        Storage::disk('s3')->put($s3Key, file_get_contents($cleanFile), 'public');
                    } catch (\Exception $s3e) {
                        // Mirror failure is non-critical
                    }
                    */

                } catch (\Exception $e) {
                    Log::warning("OpticVault Cloud Failed: " . $e->getMessage() . ". Attempting secondary storages.");
                    
                    $fileName = uniqid('fallback_') . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $file->getClientOriginalName());
                    $relativePath = 'images/' . date('Y/m') . '/' . $fileName;

                    // DISABLED: LocalStack S3 fallback causes 3-minute timeouts if container is missing.
                    /*
                    try {
                        Storage::disk('s3')->put($relativePath, file_get_contents($cleanFile), 'public');
                        $path = $relativePath;
                        Log::info("Fallback upload to LocalStack S3 successful: {$relativePath}");
                    } catch (\Exception $s3e) {
                        Log::warning("LocalStack S3 fallback failed: " . $s3e->getMessage() . ". Final fallback to LOCAL Public disk.");
                    }
                    */
                    
                    // Final Fallback: Store on local public disk for instant visibility
                    Storage::disk('public')->put($relativePath, file_get_contents($cleanFile));
                    $path = $relativePath;
                    Log::info("Final local fallback successful: {$relativePath}");
                }
            }


            // 4. Save to Database — normalized over 5 tables

            // 4a. Core image record
            $image = Image::create([
                'user_id'   => $userId,
                'album_id'  => $data['album_id'] ?? null,
                'title'     => $data['title'] ?? 'OpticVault',
                'filename'  => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'size'      => $file->getSize(),
                'privacy'   => $data['privacy'] ?? 'public',
            ]);

            // 4b. Storage (paths & cloud)
            $image->storage()->updateOrCreate(['image_id' => $image->id], [
                'path'                => $path,
                'original_path'       => $originalPath,
                'imagekit_file_id'    => $imagekitFileId,
                'imagekit_file_path'  => $imagekitFilePath,
                'md5_hash'            => $moderationResult['metadata']['hash'] ?? md5_file($file->getRealPath()),
            ]);

            // 4c. Technical EXIF/specs
            $image->meta()->updateOrCreate(['image_id' => $image->id], [
                'technical_specs' => $specs,
            ]);

            // 4d. Moderation status
            $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                'status'             => $moderationResult['status'],
                'is_sensitive'       => $moderationResult['is_sensitive'] ?? false,
                'is_visible'         => $moderationResult['is_visible'] ?? true,
                'sensitivity_reason' => $moderationResult['reason'] ?? null,
                'ai_metadata'        => $moderationResult['metadata'] ?? [],
            ]);

            // 4e. Intelligence: Auto-Tagging & Categorization (v14.1)
            $aiData = $moderationResult['metadata'] ?? [];
            $tags = $aiData['tags'] ?? [];
            
            if (!empty($tags)) {
                $image->syncTags($tags);
                $image->update(['labels' => $tags]);
            }

            // Save to polymorphic MediaAiMetadata table for UI & Advanced Filtering
            $image->aiMetadata()->updateOrCreate(['media_id' => $image->id, 'media_type' => Image::class], [
                'driver_name'      => $moderationResult['driver'] ?? 'unknown',
                'raw_results'      => $aiData['raw_results'] ?? $aiData,
                'extracted_tags'   => $tags,
                'quality_grade'    => $aiData['quality_grade'] ?? 'high_quality',
                'category'         => $aiData['category'] ?? 'other',
                'is_sensitive'     => $moderationResult['is_sensitive'] ?? false,
                'confidence_score' => $aiData['confidence_score'] ?? 1.0,
            ]);

            // 4f. Settings/permissions
            $image->settings()->updateOrCreate(['image_id' => $image->id], [
                'allow_download'        => isset($data['allow_download']),
                'watermark_on_download' => isset($data['watermark_on_download']),
            ]);

            // Refresh so proxy accessors work correctly
            $image->load(['storage', 'meta', 'moderation', 'settings', 'tags', 'aiMetadata']);

            // 5. Notify Super Admins if Quarantined
            if ($image->status === Image::STATUS_REJECTED) {
                try {
                    $admins = \App\Models\User::where('role', 'super_admin')->get();
                    if ($admins->isNotEmpty()) {
                        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\HighRiskImageUploaded($image));
                    }
                } catch (\Exception $e) {
                    Log::error("Failed to notify Super Admins: " . $e->getMessage());
                }
            }

            // 6. Dispatch AI Classification Job (non-blocking, background)
            // Only for approved / pending_review images (not quarantined ones)
            if ($image->status !== Image::STATUS_REJECTED) {
                AnalyzeImageLabelsJob::dispatch($image->id)
                    ->onQueue('default')
                    ->delay(now()->addSeconds(5)); // Small delay so DB is fully committed
            }

            // 7. Cleanup local temporal file
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
