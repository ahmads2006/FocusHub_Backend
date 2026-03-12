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
        $photographer = $image->user->name;
        $date = $image->created_at->format('Y-m-d');
        $text = "© {$photographer} | FocusHub | {$date}";

        $width = $interventionImage->width();
        $height = $interventionImage->height();

        // Calculate font size based on image width (roughly 1.5% of width)
        $fontSize = max(14, (int)($width * 0.015));
        $padding = (int)($fontSize * 1.5);

        // 1. Draw subtle background bar for readability
        $barHeight = (int)($fontSize * 2.5);
        $interventionImage->drawRectangle($width - ($padding * 2 + (strlen($text) * $fontSize * 0.6)), $height - $barHeight - $padding, function ($draw) use ($width, $height, $padding, $barHeight) {
            $draw->background('rgba(0, 0, 0, 0.4)'); // 40% opacity black
        });

        // Simplified for Intervention V3 syntax (using closure for styling)
        $interventionImage->text($text, $width - $padding, $height - (int)($padding * 1.2), function (FontFactory $font) use ($fontSize) {
            $font->filename(public_path('fonts/Montserrat-Medium.ttf')); // Assuming font is available
            $font->size($fontSize);
            $font->color('rgba(255, 255, 255, 0.5)'); // 50% opacity white
            $font->align('right');
            $font->valign('bottom');
        });

        return $interventionImage;
    }
}
