<?php

namespace App\Services\Security;

use App\Models\Image;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

class WatermarkService
{
    /**
     * Apply a dynamic watermark to the image.
     * Only ONE mode is active: either text OR logo (never both).
     * Always positioned at the bottom-right corner.
     */
    public function apply(Image $image, ImageInterface $interventionImage): ImageInterface
    {
        $user = $image->user;
        $width = $interventionImage->width();
        $height = $interventionImage->height();

        // Determine which single mode is active (text XOR logo)
        $mode = $user->watermark_mode ?? 'text'; // 'text' or 'logo'
        $opacity = $user->watermark_opacity ?? 0.5;

        // Base metrics
        $fontSize = max(14, (int)($width * 0.018));
        $padding = (int)($fontSize * 2);

        if ($mode === 'logo' && $user->watermark_logo) {
            // ── LOGO MODE: Bottom-Right Corner ──
            $this->applyLogoWatermark($interventionImage, $user, $width, $height, $padding, $opacity);
        } else {
            // ── TEXT MODE: Bottom-Right Corner ──
            $this->applyTextWatermark($interventionImage, $image, $user, $width, $height, $padding, $fontSize, $opacity);
        }

        return $interventionImage;
    }

    /**
     * Draw text watermark at bottom-right corner.
     */
    protected function applyTextWatermark(ImageInterface $img, Image $image, $user, int $width, int $height, int $padding, int $fontSize, float $opacity): void
    {
        $photographer = $user->watermark_text ?: $user->name;
        $date = $image->created_at ? $image->created_at->format('Y-m-d') : date('Y-m-d');
        $text = "© {$photographer} | {$date}";

        $textColor = $user->watermark_text_color ?: 'rgba(255, 255, 255, 0.6)';

        // Position: Bottom-Right
        $x = $width - $padding;
        $y = $height - $padding;

        $img->text($text, $x, $y, function (FontFactory $font) use ($fontSize, $textColor) {
            $fontPaths = [
                public_path('fonts/Montserrat-Medium.ttf'),
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            ];

            foreach ($fontPaths as $path) {
                if (file_exists($path)) {
                    $font->filename($path);
                    break;
                }
            }

            $font->size($fontSize);
            $font->color($textColor);
            $font->align('right');
            $font->valign('bottom');
        });
    }

    /**
     * Draw logo watermark at bottom-right corner.
     */
    protected function applyLogoWatermark(ImageInterface $img, $user, int $width, int $height, int $padding, float $opacity): void
    {
        $logoPath = $user->watermark_logo;

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
            return;
        }

        $logoData = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);

        $manager = \Intervention\Image\ImageManager::gd();
        $logo = $manager->read($logoData);

        // Resize logo to roughly 10% of image width
        $logoWidth = (int)($width * 0.12);
        $logo->scale(width: $logoWidth);

        // Position: Bottom-Right with padding
        $img->place(
            $logo,
            'bottom-right',
            offset_x: $padding,
            offset_y: $padding,
            opacity: (int)($opacity * 100)
        );
    }
}
