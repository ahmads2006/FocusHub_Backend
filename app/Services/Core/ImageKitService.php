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
    public function getWatermarkedUrl(string $path, string $textOrLogo, bool $signed = true, int $expireMinutes = 10, int $fontSize = 600, string $color = 'FFFFFF', string $type = 'text', int $imgWidth = 0, int $imgHeight = 0): string
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

        // ─── Smart Dynamic Sizing & Positioning based on Image Dimensions ───
        // Fallback to standard 1920x1080 if dimensions are unknown/missing
        $width = $imgWidth > 0 ? $imgWidth : 1920;
        $height = $imgHeight > 0 ? $imgHeight : 1080;

        // Dynamic Font Size: ~2.0% of the image's width (ensuring readability)
        $ikFontSize = max(20, (int)($width * 0.020));

        // Dynamic Padding/Margin from edges: ~3.0% of the image's width (avoiding edge-hugging)
        $padding = max(20, (int)($width * 0.030));

        if ($type === 'logo') {
            // ─── IMAGE OVERLAY ───
            // Replace slashes with @@ for ImageKit image overlay paths
            $logoPath = str_replace('/', '@@', ltrim($textOrLogo, '/'));
            // Proportional logo scale factor: 12% of image width
            $scaleFactor = 0.12;

            // Anchor bottom_right, using negative coordinates for padding (lx-N{$padding}, ly-N{$padding})
            // ImageKit ignores lfo when lx/ly are specified, so we omit lfo and use N prefix for negative offsets.
            $rawTransformation = "l-image,i-{$logoPath},w-bw_mul_{$scaleFactor},o-{$opacityPercent},lx-N{$padding},ly-N{$padding},l-end";
        } else {
            // ─── TEXT OVERLAY ───
            // ImageKit requires URL-safe base64 for the ie- parameter
            $base64Text = rtrim(strtr(base64_encode($textOrLogo), '+/', '-_'), '=');

            // For text overlays: opacity is embedded in co- as 8-char hex (RRGGBBAA)
            $alphaHex = str_pad(dechex(round($opacityPercent / 100 * 255)), 2, '0', STR_PAD_LEFT);
            $colorWithAlpha = $hexColor . $alphaHex;

            $fontFamilyStr = "";
            $bgStr = "";

            if ($type === 'sig') {
                // Signature cursive font
                $fontFamilyStr = ",ff-Caveat";
            } elseif ($type === 'glass') {
                // Glassmorphic rounded background badge
                // bg-00000044 = semi-transparent black background, rad-10 = rounded corners
                $bgStr = ",bg-00000044,rad-10,pa-15";
            }

            // Anchor bottom_right, using negative coordinates for padding (lx-N{$padding}, ly-N{$padding})
            // ImageKit ignores lfo when lx/ly are specified, so we omit lfo and use N prefix for negative offsets.
            $rawTransformation = "l-text,ie-{$base64Text},fs-{$ikFontSize},co-{$colorWithAlpha}{$fontFamilyStr}{$bgStr},lx-N{$padding},ly-N{$padding},l-end";
        }

        Log::info("ImageKit Watermark Transform: type={$type}, raw={$rawTransformation}, resolution={$width}x{$height}");

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