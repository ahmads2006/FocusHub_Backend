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
    public function getWatermarkedUrl(string $path, string $textOrLogo, bool $signed = true, int $expireMinutes = 10, int $fontSize = 600, string $color = 'FFFFFF', string $type = 'text'): string
    {
        if ($type === 'logo') {
            // For logo, we use the imagekit format for image overlays: l-image,i-<image_path>
            // We need to replace slashes in the path with @@ for ImageKit image overlays
            $logoPath = str_replace('/', '@@', ltrim($textOrLogo, '/'));
            
            // Adjust width based on fontSize (treating fontSize as a relative width for the logo)
            // e.g., w-150 means 150px width. If user selected 80 (default), maybe map that to 150px.
            $logoWidth = (int) ($fontSize * 2); 
            
            // Apply opacity
            $opacity = hexdec(substr($color, 6, 2)); // Extract alpha from FFFFFFB3
            if ($opacity === 0) $opacity = 255;
            $opacityPercentage = round(($opacity / 255) * 100);
            
            $rawTransformation = "l-image,i-{$logoPath},w-{$logoWidth},o-{$opacityPercentage},lfo-bottom_right,pa-40,l-end";
        } else {
            // Text Watermark
            $rawTransformation = 'l-text,ie-' . urlencode(base64_encode($textOrLogo)) . ",fs-{$fontSize},co-{$color},lfo-bottom_right,pa-40,l-end";
        }

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
                    'raw' => $rawTransformation
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