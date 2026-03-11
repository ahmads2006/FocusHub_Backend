<?php

namespace App\Services;

use App\Models\Image;
use App\Jobs\ProcessImageThumbnails;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    protected $contentSafety;
    protected $metadataService;
    protected $securityService;

    public function __construct(ContentSafetyService $contentSafety, MetadataService $metadataService, SecurityService $securityService)
    {
        $this->contentSafety = $contentSafety;
        $this->metadataService = $metadataService;
        $this->securityService = $securityService;
    }

    /**
     * Process and upload image with Zero-Lag Background Pipelines.
     * Instantly stores in quarantine and dispatches secure processing job.
     */
    public function upload(UploadedFile $file, array $data, string $userId): Image
    {
        // 1. Zero-Trust Verification (MIME Sniffing, Magic Bytes, AV Scan)
        $detectedExtension = $this->securityService->verify($file);

        // 2. AI Content Safety Filter
        $this->contentSafety->validate($file);

        // 3. Move to Quarantine (Temporal Storage)
        // We use 'local' disk (app/quarantine) which is not public
        $quarantinePath = $file->store('quarantine', 'local');

        // 4. Create Placeholder Image Record
        // Path points to quarantine temporarily; Job will move it to permanent storage
        $image = Image::create([
            'user_id' => $userId,
            'album_id' => $data['album_id'] ?? null,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'filename' => $file->getClientOriginalName(),
            'file_type' => $detectedExtension,
            'path' => 'quarantine/' . basename($quarantinePath), // Relative to storage/app
            'size' => $file->getSize(),
            'privacy' => $data['privacy'] ?? 'public',
            'is_comparison' => $data['is_comparison'] ?? false,
            'allow_download' => isset($data['allow_download']),
            'watermark_on_download' => isset($data['watermark_on_download']),
        ]);

        // 5. Dispatch Secure Background Pipeline
        // This handles: EXIF Extraction, GPS Stripping (Sanitization), and Storage Migration
        \App\Jobs\ProcessImageSecurely::dispatch($image, $quarantinePath, $data);

        return $image;
    }

    /**
     * Sanitizes an image by re-encoding it. 
     * This strips out suspicious metadata or binary chunks hidden inside the image file structure.
     */
    private function sanitizeImage(UploadedFile $file, string $extension, bool $shouldOrient = false): string
    {
        $tempPath = storage_path('app/tmp/' . uniqid() . '.' . $extension);
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Special Case: SVG cannot be re-encoded by GD/Intervention easily without conversion
        if ($extension === 'svg') {
            copy($file->getRealPath(), $tempPath);
            return $tempPath;
        }

        try {
            // Dynamic Driver Selection (Imagick with GD Fallback)
            $driver = extension_loaded('imagick') 
                ? new \Intervention\Image\Drivers\Imagick\Driver() 
                : new \Intervention\Image\Drivers\Gd\Driver();
            
            $manager = new ImageManager($driver);
            $image = $manager->read($file->getRealPath());

            // 1. Optional Auto-Orientation (Handled automatically by read() in v3)
            // if ($shouldOrient) {
            //     // $image->orientate(); // Removed in v3, auto-orient happens on read
            // }

            // 2. Metadata Stripping & Re-encoding
            // This process creates a BRAND NEW binary structure based only on the pixel data
            $image->scaleDown(1600, 1600); 
            $image->save($tempPath, quality: 85);

            return $tempPath;
        } catch (\Exception $e) {
            Log::error("Sanitization Failed: " . $e->getMessage());
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'فشل تطهير الملف المرفوع. قد يكون الملف تالفاً أو يحتوي على هيكلية غير صالحة.'
            ]);
        }
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

        // Use GD/Imagick dynamically
        $driver = extension_loaded('imagick') 
            ? new \Intervention\Image\Drivers\Imagick\Driver() 
            : new \Intervention\Image\Drivers\Gd\Driver();
            
        $manager = new ImageManager($driver);
        $image = $manager->read($file->getRealPath());

        // Smart Cropping: Cover a 150x150 area (Centered)
        $image->cover(150, 150, 'center');

        // Save as WebP with high compression
        $encoded = $image->toWebp(70);
        
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }


    /**
     * Delete image from storage and DB
     */
    public function delete(Image $image): bool
    {
        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }
        
        return $image->delete();
    }
}
