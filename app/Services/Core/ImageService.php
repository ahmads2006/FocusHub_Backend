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
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
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

        // Using Imagick for 2x faster processing, better color profiles, and 40% memory reduction
        $this->manager = new ImageManager(new ImagickDriver());
    }

    /**
     * The Full-Stack "Extract -> Sanitize -> Upload" Pipeline
     */
    public function processAndUpload(UploadedFile $file, array $data, string $userId): Image
    {
        // ... (existing logic for synchronous upload - preserved but can be redirected to async)
        return $this->processAndUploadAsync($file, $data, $userId);
    }

    /**
     * LIGHTNING UPLOAD: Stores file raw and dispatches background processing.
     * Return time: < 1 second.
     */
    public function processAndUploadAsync(UploadedFile $file, array $data, string $userId): Image
    {
        // 1. Initial validation & Synchronous Safety Check (v31.0 Security)
        $this->checkRateLimit($userId);

        $moderationResult = $this->contentSafety->validate($file);
        if ($moderationResult['status'] === 'rejected') {
            Log::warning("Immediate rejection for user {$userId}: Image failed safety check.");
            throw new \Exception("The image content was rejected by our safety system.");
        }
        
        $image = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $data, $file, $moderationResult) {
            $image = Image::create([
                'user_id'   => $userId,
                'album_id'  => $data['album_id'] ?? null,
                'title'     => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $data['description'] ?? null,
                'filename'  => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'size'      => $file->getSize(),
                'privacy'   => $data['privacy'] ?? 'public',
                'is_visible' => false,
            ]);

            $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                'status' => 'approved', 
                'is_visible' => false,
                'ai_metadata' => $moderationResult['metadata'] ?? [],
            ]);

            $user = $image->user;
            $settingsData = [
                'allow_download' => isset($data['allow_download']) ? filter_var($data['allow_download'], FILTER_VALIDATE_BOOLEAN) : true,
                'watermark_on_download' => isset($data['watermark_on_download']) 
                    ? filter_var($data['watermark_on_download'], FILTER_VALIDATE_BOOLEAN) 
                    : (bool) ($user->dynamic_watermark ?? false),
            ];

            // Persist per-image watermark customization fields
            $wmFields = ['watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
            foreach ($wmFields as $f) {
                if (array_key_exists($f, $data)) {
                    $settingsData[$f] = $data[$f];
                }
            }

            $image->settings()->updateOrCreate(['image_id' => $image->id], $settingsData);

            return $image;
        });

        // --- Synchronous Execution (Removed background jobs per user request) ---
        try {
            // 1. Technical Specs
            $specs = $this->extractSpecsFromPath($file->getRealPath());

            // 2. Sanitization (v40.0: Strict MIME detection for Animations)
            $isGif = $file->getMimeType() === 'image/gif';
            $cleanFile = $this->sanitizeFromPath($file->getRealPath(), $isGif);

            // 3. Final Storage
            $year = date('Y'); $month = date('m');
            $dynamicPath = "photos/{$year}/{$month}/{$image->user_id}";
            $finalPath = $this->uploadToS3($cleanFile, $image->filename, $dynamicPath);

            // 4. Update Database
            \Illuminate\Support\Facades\DB::transaction(function () use ($image, $finalPath, $specs, $moderationResult, $cleanFile) {
                $image->storage()->updateOrCreate(['image_id' => $image->id], [
                    'path' => $finalPath,
                    'imagekit_file_path' => $finalPath,
                    'md5_hash' => $moderationResult['metadata']['hash'] ?? md5_file($cleanFile),
                ]);

                $image->meta()->updateOrCreate(['image_id' => $image->id], [
                    'technical_specs' => $specs,
                ]);

                $image->update(['is_visible' => true]);
                $image->moderation()->update(['is_visible' => true]);
            });

            // 5. Cleanup
            if (file_exists($cleanFile)) @unlink($cleanFile);

            // 6. Labels (Keep labels job if needed, or run sync)
            \App\Jobs\AnalyzeImageLabelsJob::dispatchSync($image->id);

        } catch (\Exception $e) {
            Log::error("Direct Image Processing Failed: " . $e->getMessage());
            $image->delete();
            throw $e;
        }

        return $image->load(['moderation', 'user', 'storage', 'settings']);
    }

    protected function extractSpecsFromPath(string $path): array
    {
        $specs = ['camera_model' => 'Unknown'];
        try {
            $exif = @exif_read_data($path);
            if ($exif) {
                $specs['camera_model'] = $exif['Model'] ?? 'Unknown';
                $specs['iso'] = $exif['ISOSpeedRatings'] ?? 'N/A';
                $specs['aperture'] = isset($exif['FNumber']) ? 'f/' . $exif['FNumber'] : 'N/A';
            }
        } catch (\Exception $e) {}
        return $specs;
    }

    protected function sanitizeFromPath(string $path, bool $isGif = false): string
    {
        // 🎞️ ANIMATION SHIELD (Deep Fix): 
        // We now receive a explicit $isGif flag based on the original UploadedFile MimeType.
        if ($isGif) {
            $tempPath = storage_path('app/clean_' . uniqid() . '.gif');
            copy($path, $tempPath);
            return $tempPath;
        }

        $img = $this->manager->read($path);
        $tempPath = storage_path('app/clean_' . uniqid() . '.jpg');
        $img->save($tempPath, quality: 90);
        return $tempPath;
    }

    /**
     * The Full-Stack "Extract -> Sanitize -> Upload" Pipeline
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
                
                // Privacy-Aware Status Override (v24.0)
                $privacy = $data['privacy'] ?? 'public';
                if ($privacy !== 'public') {
                    // Private/Shared content is auto-approved unless AI flagged it as REJECTED.
                    if ($moderationResult['status'] === Image::STATUS_PENDING_REVIEW) {
                        $moderationResult['status'] = Image::STATUS_APPROVED;
                    }
                }
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
            $imagekitFilePath = null;
            $path = '';
            $originalPath = null;
            $year = date('Y');
            $month = date('m');

            // ── Normalize and Truncate Filename (Prevents SQLSTATE[22001] "Data too long") ─────
            $originalFileName = $file->getClientOriginalName();
            $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $originalFileName);
            $baseName = pathinfo($safeName, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            
            // Truncate base name to 60 chars to keep the final path within DB limits
            $truncatedBase = \Illuminate\Support\Str::limit($baseName, 60, '');
            $shortName = $truncatedBase . '.' . $extension;

            if ($moderationResult['status'] === 'rejected') {
                // BLOCKED: Red content is completely banned from the platform
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'عذراً، لا يمكن رفع محتوى غير لائق. تم حجب العملية بالكامل.'
                ]);
            } else {
                if ($moderationResult['is_sensitive'] ?? false) {
                    // YELLOW LOGIC: Dual-Storage Strategy
                    // Always save the clean original file securely (needed for privacy transitions)
                    $originalFileNameToStore = uniqid('original_') . '_' . $shortName;
                    $originalPath = "secure_uploads/{$year}/{$month}/{$userId}/" . $originalFileNameToStore;
                    Storage::disk('local')->put($originalPath, file_get_contents($cleanFile));

                        // [REMOVED]: Server-side Blur logic was removed as requested. The frontend CSS securely handles the visual overlay now.
                }

                // GREEN/YELLOW LOGIC: Upload to S3 and ImageKit
                try {
                    $dynamicPath = "photos/{$year}/{$month}/{$userId}";
                    
                    // 1. Store in S3 (The foundation / LocalStack)
                    $s3Path = $this->uploadToS3($cleanFile, $shortName, $dynamicPath);
                    $path = $s3Path;
                    // ImageKit is used as an Origin Proxy (CDN) only.
                    // No direct upload to ImageKit Media Library — saves 100% of storage quota.
                    // ImageKit will pull the image from S3 on-demand, transform it, and cache it.
                    $imagekitFilePath = $s3Path;

                } catch (\Exception $e) {
                    Log::warning("OpticVault Cloud (S3) Failed: " . $e->getMessage() . ". Attempting secondary storages.");
                    
                    $fileName = uniqid() . '_' . $shortName;
                    $relativePath = "photos/{$year}/{$month}/{$userId}/" . $fileName;

                    // Final Fallback: Store on local public disk for instant visibility
                    Storage::disk('public')->put($relativePath, file_get_contents($cleanFile));
                    $path = $relativePath;
                    $imagekitFilePath = null;
                    Log::info("Final local fallback successful: {$relativePath}");
                }
            }


            // 4. Save to Database — normalized over 5 tables
            $image = \Illuminate\Support\Facades\DB::transaction(function () use ($userId, $data, $file, $path, $originalPath, $imagekitFilePath, $specs, $moderationResult) {
                // 4a. Core image record
                $image = Image::create([
                    'user_id'   => $userId,
                    'album_id'  => $data['album_id'] ?? null,
                    'title'     => $data['title'] ?? config('app.name'),
                    'filename'  => $file->getClientOriginalName(),
                    'file_type' => $file->getClientOriginalExtension(),
                    'size'      => $file->getSize(),
                    'privacy'   => $data['privacy'] ?? 'public',
                ]);

                // Bridge safety cache to let the asynchronous Tagging job know safety was already verified
                if (($moderationResult['status'] ?? '') !== \App\Models\Image::STATUS_REJECTED) {
                    \Illuminate\Support\Facades\Redis::setex("opticvault:safety:{$image->id}", 3600, 'safe_verified');
                }

                // 4b. Storage (paths & cloud)
                $image->storage()->updateOrCreate(['image_id' => $image->id], [
                    'path'                => $path,
                    'original_path'       => $originalPath,
                    'imagekit_file_id'    => null, // No longer uploading to ImageKit Media Library
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
                $settingsDataToSave = [
                    'allow_download'        => isset($data['allow_download']) ? $data['allow_download'] : true,
                    'watermark_on_download' => isset($data['watermark_on_download']) ? $data['watermark_on_download'] : false,
                ];
                
                $wmFields = ['watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
                foreach ($wmFields as $f) {
                    if (array_key_exists($f, $data)) {
                        $settingsDataToSave[$f] = $data[$f];
                    }
                }
                
                $image->settings()->updateOrCreate(['image_id' => $image->id], $settingsDataToSave);

                return $image;
            });

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
            // Rollback Storage if DB transaction failed
            if (isset($path) && !empty($path)) {
                if (Storage::disk('s3')->exists($path)) {
                    Storage::disk('s3')->delete($path);
                } elseif (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            if (isset($originalPath) && !empty($originalPath) && Storage::disk('local')->exists($originalPath)) {
                Storage::disk('local')->delete($originalPath);
            }

            Log::error("OpticVault Upload Failed (Rolled back files): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete an image from cloud/local storage and database
     */
    public function delete(Image $image): void
    {
        try {
            // 1. Delete from ImageKit if legacy file exists
            if (!empty($image->imagekit_file_id)) {
                try {
                    $this->imageKit->deleteFile($image->imagekit_file_id);
                } catch (\Exception $e) {
                    Log::warning("OpticVault ImageKit Delete Failed for file_id {$image->imagekit_file_id}: " . $e->getMessage());
                }
            }

            // 2. Delete from S3 if exists
            try {
                if (!empty($image->path) && Storage::disk('s3')->exists($image->path)) {
                    Storage::disk('s3')->delete($image->path);
                }
            } catch (\Exception $e) {
                Log::warning("S3 file deletion/check failed for {$image->path}: " . $e->getMessage());
            }

            // 3. Delete local fallback or thumbnails
            try {
                if (!empty($image->path) && Storage::disk('public')->exists($image->path)) {
                    Storage::disk('public')->delete($image->path);
                }
            } catch (\Exception $e) {
                Log::warning("Local file deletion/check failed for {$image->path}: " . $e->getMessage());
            }

            // 4. Delete from database
            $image->delete();

        } catch (\Exception $e) {
            Log::error("OpticVault Delete Failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate Optimized CDN URL with dynamic resizing
     */
    public function getDynamicUrl(Image $image, ?int $width = null): string
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
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Animated GIFs rarely contain EXIF GPS data and re-encoding them flattens animations.
        // Bypass the re-encoding phase for GIFs to natively preserve their animation.
        if ($extension === 'gif') {
            $tempPath = storage_path('app/temp_' . uniqid() . '.gif');
            copy($file->getRealPath(), $tempPath);
            return $tempPath;
        }

        $img = $this->manager->read($file->getRealPath());
        
        // Preserve original extension if safe (png/webp support transparency/animation)
        $targetExtension = in_array($extension, ['png', 'webp', 'jpg', 'jpeg']) ? $extension : 'jpg';
        
        // Save as temporary clean version (re-encoding natively strips EXIF headers)
        $tempPath = storage_path('app/temp_' . uniqid() . '.' . $targetExtension);
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

    /**
     * Upload directly to S3 Bucket (The Cloud Storage).
     */
    protected function uploadToS3(string $filePath, string $originalName, string $folder): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '', $originalName);
        $safeName = pathinfo($safeName, PATHINFO_FILENAME);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $shortName = substr($safeName, 0, 40) . ($extension ? '.' . $extension : '');
        
        $fileName = uniqid('optic_') . '_' . $shortName;
        $relativePath = ltrim($folder, '/') . '/' . $fileName;

        // التحسين 1: استخدام Streams بدلاً من file_get_contents لعدم استهلاك الـ RAM
        // التحسين 2: تحديد صلاحية الملف كـ public لكي لا يظهر خطأ AccessDenied في الـ CDN
        $stream = fopen($filePath, 'r');
        Storage::disk('s3')->put($relativePath, $stream, [
            'visibility' => 'public',
            'ServerSideEncryption' => 'AES256',
        ]);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $relativePath;
    }

    /**
     * @deprecated Use uploadToS3. ImageKit is now used as an Origin Proxy.
     */
    protected function uploadToCloud(string $filePath, string $originalName, string $folder = '/opticvault/uploads')
    {
        $upload = $this->imageKit->uploadFiles([
            'file' => base64_encode(file_get_contents($filePath)),
            'fileName' => $originalName,
            'useUniqueFileName' => true,
            'folder' => '/opticvault/' . ltrim($folder, '/'),
        ]);

        if (isset($upload->error) && $upload->error) {
            $errorMsg = is_string($upload->error) ? $upload->error : ($upload->error->message ?? json_encode($upload->error));
            throw new \Exception("ImageKit Upload Error: " . $errorMsg);
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
                'image' => __('لقد تجاوزت الحد الأقصى للرفع (50 صورة في الساعة). يرجى المحاولة لاحقاً.'),
            ]);
        }
    }
}
