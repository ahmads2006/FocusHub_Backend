<?php

namespace App\Services\Security;

use App\Models\Image;
use App\Models\ProtectedImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\Drivers\Gd\Driver;

class SecureShieldService
{
    protected $manager;

    public function __construct()
    {
        // Use GD as default for high compatibility, but could switch to Imagick if available
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Process image with SecureShield v2.0 (Glassmorphic + Neon-Gradient).
     */
    public function protect(Image $image, array $settings): string
    {
        $startTime = microtime(true);

        // Settings defaults
        $settings['smart_positioning'] = $settings['smart_positioning'] ?? true;
        $settings['dynamic_blending']  = $settings['dynamic_blending']  ?? true;
        $settings['digital_archiving'] = $settings['digital_archiving'] ?? true;

        // Normalization & User Preference Integration
        $settings['watermark_text']        = $settings['watermark_text']        ?? ($image->user->watermark_text ?? $image->user->name ?? 'FocusHub');
        $settings['watermark_text_color']  = $settings['watermark_text_color']  ?? ($image->user->watermark_text_color ?? '#ffffff');
        $settings['watermark_neon_color']  = $settings['watermark_neon_color']  ?? ($image->user->watermark_neon_color ?? '#800080');
        $settings['watermark_opacity']     = (float)($settings['watermark_opacity'] ?? ($image->user->watermark_opacity ?? 0.8));
        $settings['mode']                  = $settings['mode'] ?? 'signature';

        $mode     = $settings['mode'];
        $text     = $settings['watermark_text'];
        $logoPath = $settings['logo_path'] ?? null;

        // --- Resolve image data from any disk ---
        $imageData = $this->resolveImageData($image->path);
        if (!$imageData) {
            throw new \RuntimeException("Image data could not be retrieved from any disk: {$image->path}");
        }

        // 1. Check for existing protected copy (by hash + settings)
        $fileHash = md5($imageData);
        $existing = ProtectedImage::where('hash', $fileHash)
            ->whereNull('reverted_at')
            ->where('image_id', $image->id)
            ->first();

        if ($existing && Storage::disk('public')->exists($existing->path)) {
            return Storage::disk('public')->url($existing->path);
        }

        // 2. Load & Process
        $interventionImage = $this->manager->read($imageData);
        $analysis          = $this->analyzeImage($image);

        if ($mode === 'grid') {
            $this->applyTiledGrid($interventionImage, $text, $settings);
        } else {
            $position = $this->calculateSmartPosition($interventionImage, $analysis, true);
            $this->applyGlassNeonOverlay($interventionImage, $text, $logoPath, $settings, $position);
        }

        // 3. Save to public disk
        Storage::disk('public')->makeDirectory('protected');
        $filename = 'protected/' . uniqid() . '.jpg';
        $savePath = Storage::disk('public')->path($filename);
        $interventionImage->toJpeg(90)->save($savePath);

        // 4. Archive in DB
        if ($settings['digital_archiving']) {
            ProtectedImage::create([
                'image_id' => $image->id,
                'hash'     => $fileHash,
                'path'     => $filename,
                'settings' => $settings,
            ]);
        }

        $url = Storage::disk('public')->url($filename);

        $duration = (microtime(true) - $startTime) * 1000; // in milliseconds
        Log::channel('datadog')->info("SecureShield Watermarking Completed", [
            'image_id' => $image->id,
            'duration_ms' => round($duration, 2),
            'mode' => $settings['mode'] ?? 'signature',
        ]);

        return $url;
    }

    /**
     * Resolve the image data from any available disk (public, local, s3).
     * Returns the binary data of the image.
     */
    protected function resolveImageData(string $relativePath): ?string
    {
        // 1. Try the public disk
        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->get($relativePath);
        }

        // 2. Try the local (private) disk
        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->get($relativePath);
        }

        // 3. Try the s3 disk (Cloud individual uploads)
        if (Storage::disk('s3')->exists($relativePath)) {
            return Storage::disk('s3')->get($relativePath);
        }

        return null;
    }

    /**
     * Apply the Glassmorphic + Neon-Gradient overlay (Signature mode).
     * Now with v3.0 Vibrant Glass-Neon corrections.
     */
    private function applyGlassNeonOverlay(ImageInterface $img, string $text, ?string $logoPath, array $settings = [], array $position = []): void
    {
        $width = $img->width();
        $height = $img->height();
        
        // Base aesthetic values - with user overrides
        $textMainColor = $settings['watermark_text_color'] ?? '#ffffff';
        $neonColor = $settings['watermark_neon_color'] ?? '#800080';
        $globalOpacity = $settings['watermark_opacity'] ?? 0.8;
        
        $patchWidth = (int)($width * 0.35);
        $patchHeight = (int)($height * 0.12);
        
        // Ensure minimum sizes for readability
        $patchWidth = max($patchWidth, 350);
        $patchHeight = max($patchHeight, 100);
        
        // Use smart position if available, otherwise default to bottom-left
        $x = $position['x'] ?? (int)($width * 0.05);
        $y = $position['y'] ?? (int)($height * 0.83);

        // Adjust x, y to be top-left of the patch for drawing
        $x = max(0, $x - ($patchWidth / 2));
        $y = max(0, $y - ($patchHeight / 2));
        
        // 1. Frosted Glass Bounding Box
        $img->drawRectangle($x, $y, function (RectangleFactory $rect) use ($patchWidth, $patchHeight) {
            $rect->size($patchWidth, $patchHeight);
            $rect->background('rgba(255, 255, 255, 0.05)'); 
            $rect->border('rgba(255, 255, 255, 0.1)', 1);
        });

        // 2. Neon Glow Strip (Accenting the Glass)
        $img->drawRectangle($x, $y, function (RectangleFactory $rect) use ($patchHeight, $neonColor) {
            $rect->size(4, $patchHeight);
            $rect->background($neonColor); 
        });

        // 3. Text & Logo Placement
        $centerX = $x + ($patchWidth / 2);
        $centerY = $y + ($patchHeight / 2);
        
        $fontSize = (int)($patchHeight * 0.4);
        $this->drawHighEndNeonText($img, $text, $centerX, $centerY, $fontSize, $globalOpacity, $textMainColor, $neonColor);

        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $logo = $this->manager->read(Storage::disk('public')->path($logoPath));
            $logo->scale(height: (int)($patchHeight * 0.6));
            $img->place($logo, 'top-left', $x + 20, $y + ($patchHeight * 0.2), opacity: 50);
        } else {
            // Fallback: Dramatic "V" Icon Match
            $this->drawHighEndNeonText($img, 'V', $x + 40, $y + ($patchHeight / 2), (int)($patchHeight * 0.8), 0.9, $neonColor, '#ffffff');
        }
    }

    /**
     * Draw a frosted glass patch: Deep Blur + Subtle Glow.
     */
    private function drawGlassPatch(ImageInterface $img, int $x, int $y, int $w, int $h): void
    {
        $x = max(0, $x); $y = max(0, $y);
        $w = min($w, $img->width() - $x);
        $h = min($h, $img->height() - $y);

        if ($w <= 10 || $h <= 10) return;

        // Extract patch for transparency simulation
        $patch = (clone $img)->crop($w, $h, $x, $y);
        
        // v3.0: Extreme Gaussian blur for "Frosted" effect
        $patch->blur(20);
        $patch->blur(20);

        // Brightness for "Glass" look
        $patch->brightness(15);
        
        // v3.0: Premium Dual-Border (Outer white, inner subtle)
        $patch->drawRectangle(0, 0, function ($draw) use ($w, $h) {
            $draw->size($w, $h);
            $draw->border('rgba(255, 255, 255, 0.4)', 1);
        });

        // Place back
        $img->place($patch, 'top-left', $x, $y);
    }

    /**
     * Draw text with v3.0 Neon Gradient Layering.
     * Uses Purple (#800080) and Blue (#0000FF) with variable opacity.
     */
    private function drawHighEndNeonText(ImageInterface $img, string $text, int $x, int $y, int $fontSize, float $glow, string $mainColor = '#ffffff', string $neonColor = '#800080'): void
    {
        // Path discovery for v3.1 Robustness
        $fontPaths = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/ubuntu/Ubuntu-M.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'
        ];
        
        $fontPath = null;
        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                $fontPath = $path;
                break;
            }
        }

        // Convert HEX to RGB for opacity control
        $rgb = $this->hexToRgb($neonColor);

        // 1. Custom Neon Glow (Bottom Layer)
        $img->text($text, $x + 1, $y + 1, function (FontFactory $font) use ($fontSize, $fontPath, $glow, $rgb) {
            if ($fontPath) $font->filename($fontPath);
            $font->size($fontSize);
            $font->color("rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, $glow)"); 
            $font->align('center'); $font->valign('middle');
        });

        // 2. Subtle Secondary Highlight (Middle Layer)
        $img->text($text, $x - 1, $y - 1, function (FontFactory $font) use ($fontSize, $fontPath, $rgb) {
            if ($fontPath) $font->filename($fontPath);
            $font->size($fontSize);
            $font->color("rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, 0.4)"); 
            $font->align('center'); $font->valign('middle');
        });

        // 3. User Defined Main Color (Top Layer)
        $img->text($text, $x, $y, function (FontFactory $font) use ($fontSize, $fontPath, $mainColor) {
            if ($fontPath) $font->filename($fontPath);
            $font->size($fontSize);
            $font->color($mainColor); 
            $font->align('center'); $font->valign('middle');
        });
    }

    private function hexToRgb($hex): array
    {
        $hex = str_replace("#", "", $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    /**
     * Apply Tiled Grid of Glassmorphic watermarks.
     */
    private function applyTiledGrid(ImageInterface $img, array $settings): void
    {
        $text = $settings['watermark_text'] ?? 'SecureShield';
        $width = $img->width();
        $height = $img->height();
        $fontSize = max(14, (int)($width * 0.015));
        
        $stepX = (int)($width / 3.5);
        $stepY = (int)($height / 3.5);

        for ($tx = $stepX/2; $tx < $width; $tx += $stepX) {
            for ($ty = $stepY/2; $ty < $height; $ty += $stepY) {
                // Apply a smaller glass neon overlay at each tile
                $this->applyGlassNeonOverlay($img, ['x' => (int)$tx, 'y' => (int)$ty], array_merge($settings, [
                    'watermark_text' => $text,
                    'logo_path' => null, // No logo in grid mode for cleaner look
                    'dynamic_blending' => false // Speed optimization for grid
                ]));
            }
        }
    }

    /**
     * Helper to calculate luminance.
     */
    private function calculateLuminance(ImageInterface $img, int $x, int $y): int
    {
        try {
            $colorArr = $img->pickColor($x, $y)->toArray();
            return (int)(($colorArr[0] * 0.299) + ($colorArr[1] * 0.587) + ($colorArr[2] * 0.114));
        } catch (\Exception $e) {
            return 128;
        }
    }

    /**
     * Google Vision Analysis (Kept from v1 for Smart Positioning)
     */
    private function analyzeImage(Image $image): array
    {
        $apiKey = env('GOOGLE_CLOUD_VISION_KEY');
        if (empty($apiKey)) return ['text' => [], 'logos' => [], 'objects' => []];

        try {
            $imageContent = base64_encode(Storage::disk('public')->get($image->path));
            $response = Http::timeout(10)->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [['image' => ['content' => $imageContent], 'features' => [['type' => 'TEXT_DETECTION'], ['type' => 'LOGO_DETECTION'], ['type' => 'OBJECT_LOCALIZATION']]]]
            ]);

            if ($response->successful()) {
                $res = $response->json()['responses'][0] ?? [];
                return [
                    'text' => $res['textAnnotations'] ?? [],
                    'logos' => $res['logoAnnotations'] ?? [],
                    'objects' => $res['localizedObjectAnnotations'] ?? [],
                ];
            }
        } catch (\Exception $e) { Log::error("SecureShield Analysis failed: " . $e->getMessage()); }

        return ['text' => [], 'logos' => [], 'objects' => []];
    }

    private function calculateSmartPosition(ImageInterface $img, array $analysis, bool $enabled): array
    {
        $w = $img->width(); $h = $img->height();
        if (!$enabled) return ['x' => (int)($w * 0.9), 'y' => (int)($h * 0.9)];

        $regions = [
            ['x' => 0.1, 'y' => 0.1], ['x' => 0.5, 'y' => 0.1], ['x' => 0.9, 'y' => 0.1],
            ['x' => 0.1, 'y' => 0.5], ['x' => 0.5, 'y' => 0.5], ['x' => 0.9, 'y' => 0.5],
            ['x' => 0.1, 'y' => 0.9], ['x' => 0.5, 'y' => 0.9], ['x' => 0.9, 'y' => 0.9],
        ];

        $maxEntropy = -1; $best = $regions[8];

        foreach ($regions as $region) {
            $rx = (int)($region['x'] * $w);
            $ry = (int)($region['y'] * $h);
            if ($this->isRegionOccupied($rx, $ry, $analysis, $w, $h)) continue;
            
            $entropy = $this->calculateLocalEntropy($img, $rx, $ry);
            if ($entropy > $maxEntropy) { $maxEntropy = $entropy; $best = $region; }
        }

        return ['x' => (int)($best['x'] * $w), 'y' => (int)($best['y'] * $h)];
    }

    private function isRegionOccupied(int $x, int $y, array $analysis, int $w, int $h): bool
    {
        foreach (array_merge($analysis['text'], $analysis['logos']) as $item) {
            $box = $item['boundingPoly']['vertices'] ?? [];
            if (empty($box)) continue;
            $mX = min(array_column($box, 'x')); $MX = max(array_column($box, 'x'));
            $mY = min(array_column($box, 'y')); $MY = max(array_column($box, 'y'));
            if ($x > $mX - 50 && $x < $MX + 50 && $y > $mY - 50 && $y < $MY + 50) return true;
        }
        return false;
    }

    private function calculateLocalEntropy(ImageInterface $img, int $x, int $y): float
    {
        $pS = 20; $pixels = [];
        for ($i = -$pS/2; $i < $pS/2; $i++) {
            for ($j = -$pS/2; $j < $pS/2; $j++) {
                $px = max(0, min($img->width()-1, $x + $i));
                $py = max(0, min($img->height()-1, $y + $j));
                $cArr = $img->pickColor($px, $py)->toArray();
                $pixels[] = ($cArr[0] + $cArr[1] + $cArr[2]) / 3;
            }
        }
        $avg = array_sum($pixels) / count($pixels);
        $var = 0; foreach ($pixels as $p) $var += pow($p - $avg, 2);
        return (float)sqrt($var / count($pixels));
    }

    private function addNoise(ImageInterface $img, int $x, int $y, int $radius): void
    {
        for ($i = 0; $i < ($radius * $radius * 0.05); $i++) {
            $nx = $x + rand(-$radius/2, $radius/2);
            $ny = $y + rand(-$radius/2, $radius/2);
            if ($nx >= 0 && $nx < $img->width() && $ny >= 0 && $ny < $img->height()) {
                $img->drawPixel($nx, $ny, 'rgba(255, 255, 255, 0.15)');
            }
        }
    }
}
