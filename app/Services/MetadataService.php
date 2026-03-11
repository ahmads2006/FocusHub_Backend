<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class MetadataService
{
    /**
     * Extract detailed technical EXIF metadata from an uploaded image.
     * EXPLICITLY excludes GPS and personal location data for privacy.
     */
    public function extractTechnical(UploadedFile $file): array
    {
        return $this->extractTechnicalFromPath($file->getRealPath());
    }

    /**
     * Internal extraction logic from a file path.
     */
    public function extractTechnicalFromPath(string $path): array
    {
        $tech = [
            'camera_make' => null,
            'camera_model' => null,
            'lens_type' => null,
            'focal_length' => null,
            'aperture' => null,
            'shutter_speed' => null,
            'iso' => null,
            'original_creation_date' => null,
            'extra_info' => [],
        ];

        // Basic check for EXIF capability based on file extension or signature
        // For simplicity, we check extensions here, but mime_content_type is safer
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!empty($extension) && !in_array($extension, ['jpg', 'jpeg', 'tiff', 'tif'])) {
            return $tech;
        }

        try {
            $exif = @exif_read_data($path, 'EXIF', true);
            if ($exif === false) return $tech;

            // Camera Make/Model
            $tech['camera_make'] = $exif['IFD0']['Make'] ?? null;
            $tech['camera_model'] = $exif['IFD0']['Model'] ?? null;

            // Technical details
            if (isset($exif['EXIF'])) {
                $e = $exif['EXIF'];
                
                $tech['iso'] = isset($e['ISOSpeedRatings']) ? (is_array($e['ISOSpeedRatings']) ? $e['ISOSpeedRatings'][0] : $e['ISOSpeedRatings']) : null;
                $tech['shutter_speed'] = isset($e['ExposureTime']) ? $this->cleanFraction($e['ExposureTime']) : null;
                $tech['aperture'] = isset($e['FNumber']) ? 'f/' . round($this->calculateFraction($e['FNumber']), 1) : null;
                $tech['focal_length'] = isset($e['FocalLength']) ? round($this->calculateFraction($e['FocalLength'])) . 'mm' : null;
                
                if (isset($e['DateTimeOriginal'])) {
                    try {
                        $tech['original_creation_date'] = \Carbon\Carbon::parse($e['DateTimeOriginal']);
                    } catch (Exception $ce) {}
                }

                // Lens Type (Often in specific tags or MakerNotes)
                $tech['lens_type'] = $e['UndefinedTag:0xA434'] ?? $e['LensModel'] ?? null;

                // Extra technical info (excluding any GPS/Location related keys)
                $forbidden = ['GPS', 'Location', 'Latitude', 'Longitude', 'Altitude'];
                foreach ($e as $key => $value) {
                    $isForbidden = false;
                    foreach ($forbidden as $f) {
                        if (stripos((string)$key, $f) !== false) {
                            $isForbidden = true;
                            break;
                        }
                    }
                    if (!$isForbidden && !is_null($value) && !in_array($key, ['MakerNote', 'UserComment'])) {
                        $tech['extra_info'][(string)$key] = is_array($value) ? count($value) : (string)$value;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Technical EXIF Extraction Failed: ' . $e->getMessage());
        }

        return $tech;
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
