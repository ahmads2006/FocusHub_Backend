<?php

namespace App\Services;

use App\Models\BannedImageHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Process\Process;

class ContentSafetyService
{
    /**
     * Validate the file and throw an exception if unsafe.
     */
    public function validate(UploadedFile $file): void
    {
        if (!$this->isSafe($file)) {
            throw ValidationException::withMessages([
                'image' => __('هذه الصورة تخالف سياسات الموقع لمكافحة المحتوى غير اللائق أو +18، وتم حظر رفعها. يرجى اختيار صورة أخرى تناسب معاييرنا.'),
            ]);
        }
    }

    /**
     * Run all layers of the hybrid safety system
     */
    public function isSafe(UploadedFile $file): bool
    {
        $filePath = $file->getRealPath();
        
        // Ensure file exists and is readable before proceeding
        if (!file_exists($filePath)) {
            return true;
        }

        $fileHash = md5_file($filePath);

        // LAYER 1: Immediate Hash Blacklist (Zero Cost, Instant)
        if (BannedImageHash::where('hash', $fileHash)->exists()) {
            Log::warning("AI blocked image due to Blacklisted Hash: {$fileHash}");
            return false;
        }

        // LAYER 2: Local Heuristic (Fast, Free)
        if ($this->hasExcessiveSkinTones($filePath)) {
            Log::info("Local Heuristic flagged image for possible NSFW (High skin tone ratio). Let's let Google Vision decide ultimately.");
            // We don't block immediately here to avoid false positives (like sand or wood),
            // but we could if we wanted absolute strictness.
        }

        // LAYER 3: Local Offline ML Model Bridge (Fast, Free)
        // This is a placeholder bridge: if you ever drop an 'ai_filter.py' script into 'scripts/',
        // it will automatically start executing and filtering locally before hitting Google!
        $pythonScript = base_path('scripts/ai_filter.py');
        if (file_exists($pythonScript)) {
            if (!$this->runLocalPythonModel($pythonScript, $filePath)) {
                $this->banHash($fileHash, 'Local Python AI Model Reject');
                return false;
            }
        }

        // LAYER 4: Cloud API (Google Vision) (Most Accurate)
        if (!$this->runGoogleVisionCheck($file)) {
            // Auto-ban hash so we never pay Google to check this exact image again
            $this->banHash($fileHash, 'Google Vision API Reject');
            return false;
        }

        return true;
    }

    /**
     * Local Skin Tone Heuristic using Intervention Image
     */
    private function hasExcessiveSkinTones(string $filePath): bool
    {
        try {
            $manager = new ImageManager(new Driver());
            // Scale down drastically for speed (we only care about colors, not quality)
            $image = $manager->read($filePath)->scaleDown(50, 50);
            
            $width = $image->width();
            $height = $image->height();
            $totalPixels = $width * $height;
            $skinPixels = 0;

            // Basic Skin Tone RGB Heuristic (simplified for speed)
            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $color = $image->pickColor($x, $y);
                    $r = $color->red();
                    $g = $color->green();
                    $b = $color->blue();

                    // Typical Caucasian/Hispanic/Asian skin tone boundaries in RGB
                    if ($r > 95 && $g > 40 && $b > 20 && 
                        max($r, $g, $b) - min($r, $g, $b) > 15 && 
                        abs($r - $g) > 15 && $r > $g && $r > $b) {
                        $skinPixels++;
                    }
                }
            }

            $skinRatio = $skinPixels / $totalPixels;
            
            // If more than 70% of the image is skin color
            if ($skinRatio > 0.70) {
                return true;
            }

        } catch (\Exception $e) {
            Log::warning("Skin Tone check failed: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Bridge for Local Python Script
     */
    private function runLocalPythonModel(string $scriptPath, string $imagePath): bool
    {
        try {
            $process = new Process(['python3', $scriptPath, $imagePath]);
            $process->setTimeout(5); // Don't delay uploads too much
            $process->run();

            // Expected output from your python script: "SAFE" or "UNSAFE"
            $output = trim($process->getOutput());
            if ($output === 'UNSAFE') {
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Local AI script failed: " . $e->getMessage());
        }

        return true;
    }

    /**
     * Google Vision API Check
     */
    private function runGoogleVisionCheck(UploadedFile $file): bool
    {
        $apiKey = env('GOOGLE_CLOUD_VISION_KEY');

        Log::info("AI Safety: Starting Ultra-Strict check for: " . $file->getClientOriginalName());

        if (empty($apiKey)) {
            Log::warning("AI Safety: MISSING API KEY. Blocking all questionable names.");
            return !str_contains(strtolower($file->getClientOriginalName()), 'nsfw');
        }

        try {
            // STEP 0: Shrink the image locally before sending to Google. 
            // Large images are more expensive and can hit payload limits.
            $manager = new ImageManager(new Driver());
            $tempImage = $manager->read($file->getRealPath());
            $tempImage->scaleDown(800, 800); // 800px is more than enough for AI detection
            $imageData = $tempImage->toJpeg(75)->toString(); // Lower quality for speed/size
            $imageContent = base64_encode($imageData);

            // Send request to Google Vision API
            $response = Http::timeout(10)->post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [
                    [
                        'image' => [
                            'content' => $imageContent
                        ],
                        'features' => [
                            ['type' => 'SAFE_SEARCH_DETECTION'],
                            ['type' => 'LABEL_DETECTION', 'maxResults' => 30],
                            ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 20]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $res = $result['responses'][0] ?? [];
                
                // 1. SafeSearch (Emergency Strictness: block anything not 'VERY_UNLIKELY')
                $safeSearch = $res['safeSearchAnnotation'] ?? null;
                if ($safeSearch) {
                    $adult = $safeSearch['adult'] ?? 'UNKNOWN';
                    $violence = $safeSearch['violence'] ?? 'UNKNOWN';
                    $racy = $safeSearch['racy'] ?? 'UNKNOWN';

                    Log::info("AI SafeSearch: A:$adult V:$violence R:$racy");

                    $forbidden = ['POSSIBLE', 'LIKELY', 'VERY_LIKELY'];
                    
                    if (in_array($violence, $forbidden) || in_array($adult, $forbidden) || in_array($racy, $forbidden)) {
                        Log::warning("AI Safety: BLOCKED BY SAFESEARCH");
                        return false; 
                    }
                }

                // 2. Object Localization (Detect weapons by shape)
                $objects = $res['localizedObjectAnnotations'] ?? [];
                $bannedObjects = ['weapon', 'gun', 'firearm', 'pistol', 'rifle', 'shotgun', 'military vehicle', 'tank', 'missile', 'blade', 'knife'];
                
                foreach ($objects as $obj) {
                    $name = strtolower($obj['name'] ?? '');
                    $score = $obj['score'] ?? 0;
                    if ($score > 0.20 && in_array($name, $bannedObjects)) { // Threshold drastically lowered to 20%
                        Log::warning("AI Safety: BLOCKED BY OBJECT DETECTION: $name ($score)");
                        return false;
                    }
                }

                // 3. Label Detection (Deep analysis)
                $labels = $res['labelAnnotations'] ?? [];
                $bannedKeywords = [
                    'gun', 'weapon', 'firearm', 'rifle', 'pistol', 'assault rifle', 
                    'ammunition', 'machine gun', 'sniper', 'shotgun', 'military', 
                    'soldier', 'army', 'war', 'violence', 'combat', 'explosive', 'handgun',
                    'bullet', 'trigger', 'barrel', 'ordnance', 'terror', 'militia', 'terrorist',
                    'ak-47', 'armory', 'ballistics', 'gunshot', 'revolver', 'tactical'
                ];
                
                foreach ($labels as $label) {
                    $description = strtolower($label['description'] ?? '');
                    $score = $label['score'] ?? 0;
                    
                    // If Google has even a 20% suspicion it's a weapon, block it.
                    if ($score > 0.20 && (in_array($description, $bannedKeywords) || str_contains($description, 'weapon') || str_contains($description, 'gun'))) {
                        Log::warning("AI Safety: BLOCKED BY LABEL: $description ($score)");
                        return false;
                    }
                }
            } else {
                Log::error('AI Safety: VISION API FAILURE: ' . $response->body());
                return false; // EMERGENCY: Block on API failure to be safe
            }

        } catch (\Exception $e) {
            Log::error('AI Safety: SYSTEM ERROR: ' . $e->getMessage());
            return false; // EMERGENCY: Block on system error
        }

        return true;
    }

    /**
     * Cache banning Hash
     */
    private function banHash(string $hash, string $reason): void
    {
        BannedImageHash::firstOrCreate(
            ['hash' => $hash],
            ['reason' => $reason]
        );
    }
}
