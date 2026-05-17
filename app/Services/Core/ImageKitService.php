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
        // ─── Parse color and opacity from the input ───
        // Input $color may be 6-char (FFFFFF) or 8-char with alpha (FFFFFFB3)
        $cleanColor = ltrim($color, '#');
        $hexColor = substr($cleanColor, 0, 6) ?: 'FFFFFF';
        
        // Extract or default opacity (0-100)
        if (strlen($cleanColor) >= 8) {
            $alphaHex = substr($cleanColor, 6, 2);
            $alphaInt = hexdec($alphaHex);
            $opacityPercent = ($alphaInt > 0) ? (int) round(($alphaInt / 255) * 100) : 70;
        } else {
            $opacityPercent = 70; // default
        }
        $opacityPercent = max(1, min(100, $opacityPercent));

        if ($type === 'logo') {
            // ─── IMAGE OVERLAY ───
            // Replace slashes with @@ for ImageKit image overlay paths
            $logoPath = str_replace('/', '@@', ltrim($textOrLogo, '/'));
            $logoWidth = max(50, min(800, (int) ($fontSize * 2)));
            
            // For image overlays: use o- (opacity) for opacity and lx/ly for margin instead of pa
            $rawTransformation = "l-image,i-{$logoPath},w-{$logoWidth},o-{$opacityPercent},lfo-bottom_right,lx-40,ly-40,l-end";
        } else {
            // ─── TEXT OVERLAY ───
            // Cap font size to ImageKit's practical limit (10-300)
            $safeFontSize = max(10, min(300, (int) $fontSize));

            // ImageKit requires URL-safe base64 for the ie- parameter
            $base64Text = rtrim(strtr(base64_encode($textOrLogo), '+/', '-_'), '=');

            // For text overlays: opacity is embedded in co- as 8-char hex (RRGGBBAA)
            // ImageKit does NOT support a separate o- param for text layers
            $alphaHex = str_pad(dechex(round($opacityPercent / 100 * 255)), 2, '0', STR_PAD_LEFT);
            $colorWithAlpha = $hexColor . $alphaHex;

            $rawTransformation = "l-text,ie-{$base64Text},fs-{$safeFontSize},co-{$colorWithAlpha},lfo-bottom_right,pa-40,l-end";
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