<?php

namespace App\Services\AI;

use App\Models\Image;
use Illuminate\Support\Facades\Log;

/**
 * ImageEnhancementService
 *
 * Generates enhanced CDN URLs for images with quality below high_quality.
 * Enhancement is done via URL transformations (CDN-side) — never modifies stored files.
 *
 * Supported CDN providers:
 *   - ImageKit: e-sharpen, e-contrast, e-usm (unsharp mask)
 *   - Cloudinary: e_improve (auto color/contrast/lighting)
 */
class ImageEnhancementService
{
    protected \App\Services\Core\ImageKitService $imageKit;

    public function __construct(\App\Services\Core\ImageKitService $imageKit)
    {
        $this->imageKit = $imageKit;
    }

    /**
     * Generate an enhanced CDN URL for the given image.
     * Returns null if no enhancement is needed or possible.
     */
    public function enhance(Image $image): ?string
    {
        $qualityGrade = $image->aiMetadata->quality_grade
            ?? $image->quality_grade
            ?? 'high_quality';

        if ($qualityGrade === 'high_quality') {
            return null; // Already good — no enhancement needed
        }

        // Determine which CDN the image is on
        $imagekitPath = $image->imagekit_file_path ?? null;
        $storagePath = $image->path ?? null;

        if ($imagekitPath) {
            $enhancedUrl = $this->enhanceViaImageKit($imagekitPath, $qualityGrade);
        } else {
            // Attempt Cloudinary if cloud_name is configured
            $cloudName = config('services.cloudinary.cloud_name');
            if ($cloudName && $storagePath) {
                $enhancedUrl = $this->enhanceViaCloudinary($storagePath, $qualityGrade, $cloudName);
            } else {
                Log::info("ImageEnhancement: No CDN available for image {$image->id}, skipping.");
                return null;
            }
        }

        // Store the enhanced URL
        if ($enhancedUrl) {
            $image->storage()->updateOrCreate(
                ['image_id' => $image->id],
                ['enhanced_url' => $enhancedUrl]
            );

            Log::info("ImageEnhancement: Generated enhanced URL for image {$image->id}", [
                'quality_grade' => $qualityGrade,
                'enhanced_url' => $enhancedUrl,
            ]);
        }

        return $enhancedUrl;
    }

    /**
     * Generate enhanced URL via ImageKit URL transformations.
     * Returns a signed URL by default for security.
     */
    protected function enhanceViaImageKit(string $filePath, string $qualityGrade): string
    {
        if ($qualityGrade === 'low_quality') {
            // Stronger enhancement: sharpen + contrast + unsharp mask
            $transformations = [
                ['effectSharpen' => '10'],
                ['effectContrast' => '1'],
                ['effectUSM' => '2-2-0.8-0.024']
            ];
        } else {
            // Light enhancement: gentle sharpen only
            $transformations = [
                ['effectSharpen' => '5']
            ];
        }

        return $this->imageKit->getEnhancedUrl($filePath, $transformations);
    }

    /**
     * Generate enhanced URL via Cloudinary transformations.
     *
     * - medium_quality → e_improve with 50% blend
     * - low_quality    → e_improve with 80% blend + auto brightness
     */
    protected function enhanceViaCloudinary(string $publicId, string $qualityGrade, string $cloudName): string
    {
        if ($qualityGrade === 'low_quality') {
            $transformation = 'e_improve:80,e_auto_brightness,q_auto,f_auto';
        } else {
            $transformation = 'e_improve:50,q_auto,f_auto';
        }

        return "https://res.cloudinary.com/{$cloudName}/image/upload/{$transformation}/{$publicId}";
    }
}
