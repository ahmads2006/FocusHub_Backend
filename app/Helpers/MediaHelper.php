<?php

namespace App\Helpers;

class MediaHelper
{
    /**
     * Determine the aspect ratio / orientation (e.g., 16:9, Square, Portrait).
     */
    public static function getOrientation(int $width, int $height): string
    {
        if ($width === $height) {
            return 'square';
        }

        if ($width > $height) {
            return 'landscape';
        }

        return 'portrait';
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
