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
        // ─── Extract opacity from 8-char color (e.g. FFFFFFB3) ───
        // ImageKit's co- param only accepts 6-char hex; opacity goes in separate o- param
        $hexColor = substr(ltrim($color, '#'), 0, 6) ?: 'FFFFFF';
        $opacityPercentage = 70; // default

        if (strlen(ltrim($color, '#')) >= 8) {
            $alphaHex = substr(ltrim($color, '#'), 6, 2);
            $alphaInt = hexdec($alphaHex);
            $opacityPercentage = ($alphaInt > 0) ? (int) round(($alphaInt / 255) * 100) : 70;
        }

        // Clamp opacity to valid ImageKit range (1-100)
        $opacityPercentage = max(1, min(100, $opacityPercentage));

        if ($type === 'logo') {
            // For logo, we use the imagekit format for image overlays: l-image,i-<image_path>
            // We need to replace slashes in the path with @@ for ImageKit image overlays
            $logoPath = str_replace('/', '@@', ltrim($textOrLogo, '/'));
            
            // Adjust width based on fontSize (treating fontSize as a relative width for the logo)
            $logoWidth = max(50, min(800, (int) ($fontSize * 2)));
            
            $rawTransformation = "l-image,i-{$logoPath},w-{$logoWidth},o-{$opacityPercentage},lfo-bottom_right,pa-40,l-end";
        } else {
            // ─── Text Watermark ───
            // Cap font size to ImageKit's practical limit (10-300)
            $safeFontSize = max(10, min(300, (int) $fontSize));

            // ImageKit requires URL-safe base64 for the ie- parameter:
            //   Standard base64 → replace + with -, / with _, strip = padding
            $base64Text = rtrim(strtr(base64_encode($textOrLogo), '+/', '-_'), '=');

            $rawTransformation = "l-text,ie-{$base64Text},fs-{$safeFontSize},co-{$hexColor},o-{$opacityPercentage},lfo-bottom_right,pa-40,l-end";
        }

        Log::info("ImageKit Watermark Transform: type={$type}, raw={$rawTransformation}");

        return $this->imagekit->url([
            'path' => $path,
            'signed' => $signed,
            'expireSeconds' => $expireMinutes * 60,
            'transformation' => [
                [
                    'format' => 'auto',
                    'quality' => 'auto',
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