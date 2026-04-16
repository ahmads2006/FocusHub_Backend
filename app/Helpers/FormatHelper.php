<?php

namespace App\Helpers;

class FormatHelper
{
    /**
     * Format bytes into a human readable string.
     */
    public static function bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Format a large number (e.g. likes/views) into K/M format.
     */
    public static function number(int $number, int $precision = 1): string
    {
        if ($number < 1000) {
            return (string)$number;
        }

        if ($number < 1000000) {
            return round($number / 1000, $precision) . 'K';
        }

        return round($number / 1000000, $precision) . 'M';
    }
}
