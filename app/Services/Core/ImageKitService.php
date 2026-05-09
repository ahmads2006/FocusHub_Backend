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
            config('services.imagekit.public_key'),
            config('services.imagekit.private_key'),
            config('services.imagekit.url_endpoint')
        );
    }

    /**
     * Generate a secure, signed URL for an asset.
     */
    public function generateSignedUrl(string $path, array $transformations = [], int $expireMinutes = 10): string
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
        /* Original code was hardcoded to webp:
        $transformations = [
            ['format' => 'webp', 'quality' => 'auto', 'progressive' => 'true']
        ];
        */
        $transformations = [
            ['format' => 'auto', 'quality' => 'auto', 'progressive' => 'true']
        ];

        if ($width || $height) {
            $resize = [];
            if ($width)
                $resize['width'] = (string) $width;
            if ($height)
                $resize['height'] = (string) $height;
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
     * Returns a signed URL by default for maximum security to prevent manual tampering.
     */
    public function getWatermarkedUrl(string $path, string $text, bool $signed = true, int $expireMinutes = 10): string
    {
        return $this->imagekit->url([
            'path' => $path,
            'signed' => $signed,
            'expireSeconds' => $expireMinutes * 60,
            'transformation' => [
                [
                    'format' => 'auto',
                    'quality' => 'auto',
                    'progressive' => 'true'
                ],
                [
                    'overlayText' => base64_encode($text),
                    'overlayTextFontSize' => '80',
                    'overlayTextColor' => 'FFFFFF',
                    'overlayAlpha' => '80',
                    'overlayFocus' => 'bottom_right',
                    'overlayX' => '40',
                    'overlayY' => '40',
                    'overlayTextBackground' => '000000',
                    'overlayTextEncoded' => 'true',
                ]
            ],
            'queryParameters' => [
                'ik-attachment' => 'true'
            ]
        ]);
    }

    /**
     * Get an enhanced URL for an image.
     */
    public function getEnhancedUrl(string $path, array $transformations, bool $signed = true, int $expireMinutes = 60): string
    {
        return $this->imagekit->url([
            'path' => $path,
            'signed' => $signed,
            'expireSeconds' => $expireMinutes * 60,
            'transformation' => array_merge([
                ['format' => 'auto', 'quality' => 'auto', 'progressive' => 'true'] // Upgraded from webp
            ], $transformations),
        ]);
    }

}