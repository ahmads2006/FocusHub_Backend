<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadService
{
    /**
     * Process and upload image
     */
    public function upload(UploadedFile $file, array $data, string $userId): Image
    {
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

        // Extract EXIF if needed (simplified)
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
