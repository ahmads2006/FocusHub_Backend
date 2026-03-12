<?php

namespace App\Services\AI;

use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class SmartCompressionService
{
    /**
     * Determine the optimal quality and compression settings for an image.
     * Adaptive logic based on resolution and initial file size.
     *
     * @param ImageInterface $image
     * @param int|null $originalSize Size in bytes
     * @param string $variant 'large', 'medium', etc.
     * @return array
     */
    public function getOptimizationSettings(ImageInterface $image, ?int $originalSize = null, string $variant = 'gallery'): array
    {
        $width = $image->width();
        $height = $image->height();
        
        // Default target quality
        $quality = 80;

        // Adaptive Logic:
        // 1. High-Res / Large files (Detail heavy): Apply heavier compression (Lossy optimization)
        if ($width > 3000 || $height > 3000 || ($originalSize && $originalSize > 5242880)) {
            $quality = 75;
        } 
        // 2. Low-Res / Small files: Lighter compression to maintain clarity
        elseif ($width < 1200 && $height < 1200) {
            $quality = 82;
        }

        // Adjust based on variant context
        if ($variant === 'avatar') {
            $quality = 70; // High compression for tiny icons
        }

        return [
            'quality' => $quality,
            'format' => 'webp',
            'strip_metadata' => true,
            'chroma_subsampling' => '4:2:0', // Professional standard for web
        ];
    }

    /**
     * Apply advanced Imagick-specific optimizations if available.
     * This targets stripping bloat markers while keeping essence.
     */
    public function tuneImagick($imagick)
    {
        if ($imagick instanceof \Imagick) {
            // Force Chroma Subsampling to 4:2:0 for JPEG/WebP compatibility
            $imagick->setSamplingFactors(['4:2:0', '4:2:0', '4:2:0']);
            
            // Strip unnecessary profiles (APP/COM markers) that bloat file size
            $imagick->stripImage();
            
            // Optimization for web: Interlace / Plane for progressive loading
            $imagick->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
        }
    }
}
