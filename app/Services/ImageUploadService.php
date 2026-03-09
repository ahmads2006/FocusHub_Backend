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

    public function __construct(ContentSafetyService $contentSafety, MetadataService $metadataService)
    {
        $this->contentSafety = $contentSafety;
        $this->metadataService = $metadataService;
    }

    /**
     * Process and upload image
     */
    public function upload(UploadedFile $file, array $data, string $userId): Image
    {
        // 1. Run Content Safety Check before any processing
        $this->contentSafety->validate($file);

        $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
        $disk = 'public'; 
        $directory = 'images/' . date('Y/m');
        $path = $file->storeAs($directory, $filename, $disk);

        $absolutePath = Storage::disk($disk)->path($path);
        
        $manager = new ImageManager(new Driver());
        // Generate thumbnail/compress
        $imageProc = $manager->read($absolutePath);
        $imageProc->scaleDown(1200, 1200); // Higher res for "original"
        $imageProc->save($absolutePath, quality: 80);

        // Extract structured EXIF metadata
        $metadata = $this->metadataService->extract($file);

        // Keep raw EXIF as fallback if needed
        $exif = [];
        try {
            $exif = @exif_read_data($file->getRealPath());
        } catch (\Exception $e) {}

        return Image::create([
            'user_id' => $userId,
            'album_id' => $data['album_id'] ?? null,
            'title' => $data['title'] ?? $file->getClientOriginalName(),
            'description' => $data['description'] ?? null,
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'size' => $file->getSize(),
            'privacy' => $data['privacy'] ?? 'public',
            'exif_data' => $exif,
            'metadata' => $metadata,
            'is_comparison' => $data['is_comparison'] ?? false,
        ]);
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
