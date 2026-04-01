<?php

namespace App\Services\Security;

use App\Models\Image;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

class WatermarkService
{
    /**
     * Apply a dynamic text watermark to the image.
     * 
     * Format: © [Photographer Name] | FocusHub | [Date]
     */
    public function apply(Image $image, ImageInterface $interventionImage): ImageInterface
    {
        $user = $image->user;
        $width = $interventionImage->width();
        $height = $interventionImage->height();

        $useText = $user->use_text_watermark;
        $useLogo = $user->use_logo_watermark && $user->watermark_logo;

        // Base metrics
        $fontSize = max(14, (int)($width * 0.015));
        $padding = (int)($fontSize * 1.5);
        $opacity = $user->watermark_opacity ?? 0.5;

        $currentY = $height - $padding;

        // 1. Draw Text Watermark if enabled
        if ($useText) {
            $photographer = $user->watermark_text ?: $user->name;
            $date = $image->created_at->format('Y-m-d');
            $text = "© {$photographer} | {$date}";
            
            $textColor = $user->watermark_text_color ?: 'rgba(255, 255, 255, 0.5)';
            // Convert hex to rgba if needed or keep as is if Intervention handles it
            
            $interventionImage->text($text, $width - $padding, $currentY, function (FontFactory $font) use ($fontSize, $opacity, $textColor) {
                if (file_exists(public_path('fonts/Montserrat-Medium.ttf'))) {
                    $font->filename(public_path('fonts/Montserrat-Medium.ttf'));
                }
                $font->size($fontSize);
                $font->color($textColor);
                $font->align('right');
                $font->valign('bottom');
            });

            // If we have a logo too, move the Y up for the logo
            $currentY -= (int)($fontSize * 2);
        }

        // 2. Draw Logo Watermark if enabled
        if ($useLogo) {
            $logoPath = $user->watermark_logo;
            
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                $logoData = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);
                
                // Use the driver associated with the intervention image or default to GD/Imagick
                $manager = \Intervention\Image\ImageManager::gd(); // Fallback to GD
                $logo = $manager->read($logoData);
                
                // Resize logo to roughly 10% of image width
                $logoWidth = (int)($width * 0.12);
                $logo->scale(width: $logoWidth);
                
                // Position logo above text or at bottom-right
                $interventionImage->place(
                    $logo, 
                    'bottom-right', 
                    offset_x: $padding, 
                    offset_y: $height - $currentY,
                    opacity: (int)($opacity * 100) // Intervention place uses 0-100 for opacity
                );
            }
        }

        return $interventionImage;
    }
}
