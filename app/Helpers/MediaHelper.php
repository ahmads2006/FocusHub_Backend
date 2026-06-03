<?php

namespace App\Helpers;

class MediaHelper
{
    /** Default CSS aspect-ratio when dimensions are unknown */
    public const ORIENTATION_ASPECT = [
        'landscape' => '4 / 3',
        'portrait'  => '3 / 4',
        'square'    => '1 / 1',
    ];

    /**
     * Determine the aspect ratio / orientation (e.g., 16:9, Square, Portrait).
     */
    public static function getOrientation(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return 'landscape';
        }

        if ($width === $height) {
            return 'square';
        }

        if ($width > $height) {
            return 'landscape';
        }

        return 'portrait';
    }

    /**
     * Simplified CSS aspect-ratio string (e.g. "3 / 2") from pixel dimensions.
     */
    public static function toCssAspectRatio(int $width, int $height): string
    {
        if ($width <= 0 || $height <= 0) {
            return self::ORIENTATION_ASPECT['landscape'];
        }

        $divisor = self::gcd($width, $height);

        return ($width / $divisor) . ' / ' . ($height / $divisor);
    }

    /**
     * Extract width/height/orientation/aspect_ratio for gallery cards from meta payloads.
     *
     * @param  mixed  $meta  ImageMeta model, array, or null
     * @return array{width:int,height:int,orientation:string,aspect_ratio:string}
     */
    public static function resolveGalleryFrame($meta = null): array
    {
        $specs = self::extractTechnicalSpecs($meta);
        $width  = (int) ($specs['width'] ?? 0);
        $height = (int) ($specs['height'] ?? 0);

        if ($width > 0 && $height > 0) {
            $orientation = self::getOrientation($width, $height);

            return [
                'width'         => $width,
                'height'        => $height,
                'orientation'   => $orientation,
                'aspect_ratio'  => self::toCssAspectRatio($width, $height),
            ];
        }

        $orientation = 'landscape';

        return [
            'width'         => 0,
            'height'        => 0,
            'orientation'   => $orientation,
            'aspect_ratio'  => self::ORIENTATION_ASPECT[$orientation],
        ];
    }

    /**
     * @param  mixed  $meta
     * @return array<string, mixed>
     */
    public static function extractTechnicalSpecs($meta): array
    {
        if ($meta === null) {
            return [];
        }

        if (is_object($meta) && method_exists($meta, 'getAttribute')) {
            $specs = $meta->technical_specs ?? $meta->specs ?? [];
        } elseif (is_array($meta)) {
            $specs = $meta['technical_specs'] ?? $meta['specs'] ?? $meta;
        } else {
            return [];
        }

        if (is_string($specs)) {
            $decoded = json_decode($specs, true);
            $specs = is_array($decoded) ? $decoded : [];
        }

        return is_array($specs) ? $specs : [];
    }

    private static function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max(1, $a);
    }

    /**
     * Convert an RGB array array [255, 0, 0] to a Hex string #FF0000.
     */
    public static function rgbToHex(array $rgb): ?string
    {
        if (count($rgb) < 3) {
            return null;
        }
        
        return sprintf("#%02x%02x%02x", $rgb[0], $rgb[1], $rgb[2]);
    }
}
