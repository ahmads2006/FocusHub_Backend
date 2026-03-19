<?php

namespace App\Services\Core;

use ImageKit\ImageKit;
use Illuminate\Support\Facades\Log;

class ImageKitService
{
    protected ImageKit $imagekit;

    public function __construct()
    {
        $this->imagekit = new ImageKit(
            config('services.imagekit.public_key') ?? env('IMAGEKIT_PUBLIC_KEY'),
            config('services.imagekit.private_key') ?? env('IMAGEKIT_PRIVATE_KEY'),
            config('services.imagekit.url_endpoint') ?? env('IMAGEKIT_URL_ENDPOINT')
        );
    }

    /**
     * Generate a secure, signed URL for an asset.
     */
    public function generateSignedUrl(string $path, array $transformations = [], int $expireMinutes = 30): string
    {
        return $this->imagekit->url([
            'path' => $path,
            'signed' => true,
            'expireSeconds' => $expireMinutes * 60,
            'transformation' => $transformations,
        ]);
    }

    /**
     * Get an optimized URL with best-practice transformations.
     */
    public function getOptimizedUrl(string $path, ?int $width = null, ?int $height = null): string
    {
        $transformations = [
            ['format' => 'webp', 'quality' => 'auto']
        ];

        if ($width || $height) {
            $resize = [];
            if ($width) $resize['width'] = (string) $width;
            if ($height) $resize['height'] = (string) $height;
            $resize['crop'] = 'at_max';
            $transformations[] = $resize;
        }

        return $this->imagekit->url([
            'path' => $path,
            'transformation' => $transformations,
        ]);
    }

    /**
     * Apply a dynamic watermark (SecureShield) via ImageKit overlay.
     */
    public function getWatermarkedUrl(string $path, string $text): string
    {
        return $this->imagekit->url([
            'path' => $path,
            'transformation' => [
                [
                    'format' => 'webp',
                    'quality' => 'auto',
                ],
                [
                    'overlayText' => $text,
                    'overlayTextFontSize' => '30',
                    'overlayTextColor' => 'FFFFFF',
                    'overlayAlpha' => '50',
                    'overlayX' => '10',
                    'overlayY' => '10',
                ]
            ],
        ]);
    }
}
