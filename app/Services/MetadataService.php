<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MetadataService
{
    /**
     * Extract EXIF metadata from an uploaded image file.
     * Ensure it does not crash if EXIF data is missing or corrupted.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function extract(UploadedFile $file): array
    {
        $metadata = [];

        // EXIF data is generally restricted to JPEG and TIFF files.
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'tiff', 'tif'])) {
            return $metadata;
        }

        try {
            // Suppress warnings in case the EXIF data is malformed
            $exif = @exif_read_data($file->getRealPath(), 'EXIF', true);

            if ($exif !== false) {
                // Extract Camera Model
                if (isset($exif['IFD0']['Model'])) {
                    $metadata['CameraModel'] = trim($exif['IFD0']['Model']);
                }

                // Extract DateTimeOriginal
                if (isset($exif['EXIF']['DateTimeOriginal'])) {
                    $metadata['DateTimeOriginal'] = $exif['EXIF']['DateTimeOriginal'];
                }

                // Extract ISO
                if (isset($exif['EXIF']['ISOSpeedRatings'])) {
                    $iso = $exif['EXIF']['ISOSpeedRatings'];
                    $metadata['ISO'] = is_array($iso) ? $iso[0] : $iso;
                }

                // Extract Shutter Speed (Exposure Time)
                if (isset($exif['EXIF']['ExposureTime'])) {
                    $metadata['ShutterSpeed'] = $this->cleanFraction($exif['EXIF']['ExposureTime']);
                }

                // Extract Aperture (F-Number)
                if (isset($exif['EXIF']['FNumber'])) {
                    $metadata['ApertureValue'] = 'f/' . round($this->calculateFraction($exif['EXIF']['FNumber']), 1);
                }
            }
        } catch (Exception $e) {
            Log::warning('EXIF Extraction Failed: ' . $e->getMessage());
        }

        return $metadata;
    }

    /**
     * Clean fractions like "10/1250" to "1/125" format
     */
    private function cleanFraction($value)
    {
        if (str_contains($value, '/')) {
            $parts = explode('/', $value);
            $numerator = (float) $parts[0];
            $denominator = (float) $parts[1];
            
            if ($numerator > 0 && $denominator > 0) {
                if ($numerator === 1.0) {
                    return "1/" . round($denominator);
                } else if ($numerator > 1 && $denominator > $numerator) {
                    return "1/" . round($denominator / $numerator);
                } else {
                    return round($numerator / $denominator, 4);
                }
            }
        }
        return $value;
    }

    /**
     * Calculate float value from a fraction string like "28/10"
     */
    private function calculateFraction($value)
    {
        if (str_contains($value, '/')) {
            $parts = explode('/', $value);
            if ((float)$parts[1] > 0) {
                return (float)$parts[0] / (float)$parts[1];
            }
            return 0;
        }
        return (float)$value;
    }
}
