<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
     * Process and upload image with Zero-Trust security layers
     */
    public function upload(UploadedFile $file, array $data, string $userId): Image
    {
        // 1. Zero-Trust Verification (MIME Sniffing, Magic Bytes, AV Scan)
        $detectedExtension = $this->securityService->verify($file);

        // 2. AI Content Safety Filter
        $this->contentSafety->validate($file);

        // 3. Sanitization (Re-encoding) 
        // We "Destroy and Recreate" the image to strip any embedded malicious blobs
        $sanitizedFile = $this->sanitizeImage($file, $detectedExtension);

        $filename = uniqid() . '_' . time() . '.' . $detectedExtension;
        $disk = 'public'; 
        $directory = 'images/' . date('Y/m');
        
        // Move sanitized file to final location
        $path = Storage::disk($disk)->putFileAs($directory, $sanitizedFile, $filename);

        // Extract metadata BEFORE we potentially strip it during compression (or use original)
        $metadata = $this->metadataService->extract($file);
        
        // Keep raw EXIF as fallback
        $exif = [];
        if (in_array($detectedExtension, ['jpg', 'jpeg', 'tiff'])) {
            try {
                $exif = @exif_read_data($file->getRealPath());
            } catch (\Exception $e) {}
        }

        // Cleanup temporary sanitized file
        @unlink($sanitizedFile);

        return Image::create([
            'user_id' => $userId,
            'album_id' => $data['album_id'] ?? null,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'filename' => $file->getClientOriginalName(),
            'file_type' => $detectedExtension,
            'path' => $path,
            'size' => filesize(Storage::disk($disk)->path($path)),
            'privacy' => $data['privacy'] ?? 'public',
            'exif_data' => $exif,
            'metadata' => $metadata,
            'is_comparison' => $data['is_comparison'] ?? false,
        ]);
    }

    /**
     * Sanitizes an image by re-encoding it. 
     * This strips out suspicious metadata or binary chunks hidden inside the image file structure.
     */
    private function sanitizeImage(UploadedFile $file, string $extension): string
    {
        $tempPath = storage_path('app/tmp/' . uniqid() . '.' . $extension);
        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        // Special Case: SVG cannot be re-encoded by GD/Intervention easily without conversion
        // SecurityService already ran a strict regex/XML check on SVG content.
        if ($extension === 'svg') {
            copy($file->getRealPath(), $tempPath);
            return $tempPath;
        }

        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file->getRealPath());

            // Re-encoding to the same or optimized format
            // This process creates a BRAND NEW binary structure based only on the pixel data
            $image->scaleDown(1600, 1600); // Standardize size
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
