<?php

namespace App\Services\AI;

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
    public function validate(UploadedFile $file): array
    {
        // We now allow all images to be "uploaded" but they will be assigned 
        // a status (approved, pending_review, rejected) which controls their visibility.
        // This allows admins to review even the rejected ones in the dashboard.
        return $this->check($file);
    }

    /**
     * Perform deep analysis and return status + metadata
     */
    public function check(UploadedFile $file): array
    {
        Log::info("AI Safety: Starting check for " . $file->getClientOriginalName());
        $filePath = $file->getRealPath();
        $fileHash = md5_file($filePath);
        $metadata = [
            'hash' => $fileHash,
            'checks' => []
        ];

        // LAYER 1: Blacklist
        if (BannedImageHash::where('hash', $fileHash)->exists()) {
            return [
                'status' => 'rejected',
                'reason' => 'Hash Blacklist',
                'metadata' => $metadata
            ];
        }

        // LAYER 1.5: Redis-Cached Moderation Results (v10.0 Optimization)
        $cachedResult = \Illuminate\Support\Facades\Cache::get("moderation_hash:{$fileHash}");
        if ($cachedResult) {
            Log::info("AI Safety: Skipping scan. Returning Redis-cached result for hash {$fileHash}");
            return array_merge($cachedResult, ['metadata' => array_merge($metadata, ['cached_redis' => true])]);
        }

        // Fallback to checking existing images in DB if Redis is empty
        $existingStorage = \App\Models\ImageStorage::where('md5_hash', $fileHash)->first();
        if ($existingStorage) {
            $existingImage = $existingStorage->image;
            if ($existingImage) {
                Log::info("AI Safety: Skipping scan. Returning DB-cached result for hash {$fileHash}");
                $dbResult = [
                    'status' => $existingImage->status,
                    'is_sensitive' => $existingImage->is_sensitive,
                    'is_visible' => $existingImage->is_visible,
                    'reason' => 'Cached Result (Duplicate Asset)',
                ];
                // Cache it in Redis for next time
                \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $dbResult, now()->addDays(7));
                return array_merge($dbResult, ['metadata' => array_merge($metadata, ['cached_db' => true])]);
            }
        }

        // LAYER 2: Heuristics
        $hasSkinFlag = $this->hasExcessiveSkinTones($filePath);
        $metadata['checks']['skin_heuristic'] = $hasSkinFlag ? 'flagged' : 'pass';

        // LAYER 3: Sightengine AI
        Log::info("AI Safety: Calling Sightengine API (Comprehensive)...");
        $sightengineRaw = $this->runSightengineRawCheck($file);
        $metadata['checks']['sightengine'] = $sightengineRaw;

        if ($sightengineRaw === null) {
            // Fail-Open Logic for Development
            return ['status' => 'approved', 'reason' => 'Fail-Open (Cloud Down)', 'metadata' => $metadata];
        }

        // --- Extract Values for Comprehensive Analysis ---
        
        // 1. Nudity & Safety
        $isSafeNudity = $sightengineRaw['nudity']['none'] ?? 0;
        $sexualDisplay = $sightengineRaw['nudity']['sexual_display'] ?? 0;

        // 2. Weapons
        $maxWeapon = max(
            $sightengineRaw['weapon']['classes']['firearm'] ?? 0,
            $sightengineRaw['weapon']['classes']['knife'] ?? 0,
            $sightengineRaw['weapon']['classes']['firearm_gesture'] ?? 0
        );

        // 3. Alcohol & Drugs
        $alcohol = $sightengineRaw['alcohol']['prob'] ?? 0;
        $drugs = max(
            $sightengineRaw['recreational_drug']['prob'] ?? 0,
            $sightengineRaw['medical']['prob'] ?? 0
        );

        // 4. Offensive & Gore (Aggressive Thresholding)
        $offensive = $sightengineRaw['offensive']['prob'] ?? 0;
        $gore = $sightengineRaw['gore']['prob'] ?? 0;

        // 5. Sensitive Data (PII)
        $hasSensitiveData = false;
        $piiModels = ['phones', 'links', 'emails'];
        foreach ($piiModels as $pii) {
            if (isset($sightengineRaw[$pii])) {
                if (($sightengineRaw[$pii]['prob'] ?? 0) > 0.1) $hasSensitiveData = true;
                if (!empty($sightengineRaw[$pii]['matches'])) $hasSensitiveData = true;
                if (!empty($sightengineRaw[$pii]['detected'])) $hasSensitiveData = true;
            }
        }
        if (isset($sightengineRaw['text']['profanity']) && count($sightengineRaw['text']['profanity']) > 0) {
            $hasSensitiveData = true;
        }
        if (isset($sightengineRaw['text']['personal']) && count($sightengineRaw['text']['personal']) > 0) {
            $hasSensitiveData = true;
        }

        // Log the AI scores for transparency
        Log::info('Sightengine Raw Response: ' . json_encode($sightengineRaw));
        Log::info('Sightengine V3 scores for ' . $file->getClientOriginalName() . ': ' . json_encode([
            'safe_nudity' => $isSafeNudity,
            'max_weapon' => $maxWeapon,
            'gore' => $gore,
            'alcohol' => $alcohol,
            'pii_detected' => $hasSensitiveData
        ]));

        // --- Decision Logic (3-Tier Image Moderation System) ---

        // 1. RED (High Risk / Critical)
        // Any score failing the Yellow threshold (e.g., Nudity < 0.25 or Weapons > 0.75)
        // Adjusted Gore strictly: > 0.40 is considered highly graphic.
        if ($isSafeNudity < 0.25 || $maxWeapon > 0.75 || $gore > 0.40) {
            $this->banHash($fileHash, 'Strict Policy Violation (Red Zone)', $sightengineRaw);
            $result = [
                'status' => 'rejected',
                'is_sensitive' => true,
                'is_visible' => false,
                'reason' => 'Strict Violation',
            ];
            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return array_merge($result, ['metadata' => $metadata]);
        }

        // 2. YELLOW (Medium Risk / Sensitive)
        // nudity.none between 0.25 - 0.65 OR weapon/gore between 0.20 - 0.40 OR PII/Alcohol detected.
        if ($isSafeNudity <= 0.65 || $maxWeapon >= 0.20 || $gore >= 0.20 || $alcohol >= 0.20 || $drugs >= 0.20 || $hasSensitiveData) {
            $result = [
                'status' => 'pending_review',
                'is_sensitive' => true,
                'is_visible' => true,
                'reason' => 'Sensitive Content/Review Needed (Yellow Zone Triggered)',
            ];
            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return array_merge($result, ['metadata' => $metadata]);
        }

        // 3. GREEN (Low Risk / Safe) - Primary Check
        // LAYER 3: Local Python ML (Secondary Check for GREEN)
        Log::info("AI Safety: Image passed Sightengine. Running Local Python ML as secondary check...");
        $pythonScript = base_path('scripts/ai_filter.py');
        $pythonResult = 'skipped';
        if (file_exists($pythonScript)) {
            $pythonResult = $this->runLocalPythonModel($pythonScript, $filePath);
        }
        $metadata['checks']['local_ml'] = $pythonResult;
        Log::info("AI Safety: Python ML Result: " . $pythonResult);

        if ($pythonResult === 'UNSAFE') {
            $this->banHash($fileHash, 'Local AI Filter Reject (Post-Sightengine)', $sightengineRaw);
            $result = [
                'status' => 'rejected',
                'is_sensitive' => true,
                'is_visible' => false,
                'reason' => 'Local AI Strong Reject',
            ];
            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return array_merge($result, ['metadata' => $metadata]);
        }

        if ($pythonResult === 'SUSPICIOUS') {
            $result = [
                'status' => 'pending_review',
                'is_sensitive' => true,
                'is_visible' => true,
                'reason' => 'Local AI Suspicious',
            ];
            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return array_merge($result, ['metadata' => $metadata]);
        }

        $result = [
            'status' => 'approved',
            'is_sensitive' => false,
            'is_visible' => true,
            'reason' => 'Safe (Passed Sightengine & Local ML)',
        ];
        \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
        return array_merge($result, ['metadata' => $metadata]);
    }

    /**
     * Local Skin Tone Heuristic using Intervention Image
     */
    private function hasExcessiveSkinTones(string $filePath): bool
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($filePath)->scaleDown(50, 50);
            
            $width = $image->width();
            $height = $image->height();
            $totalPixels = $width * $height;
            $skinPixels = 0;

            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $color = $image->pickColor($x, $y);
                    $r = $color->red()->value();
                    $g = $color->green()->value();
                    $b = $color->blue()->value();

                    $isSkin = ($r > 95 && $g > 40 && $b > 20 && 
                              max($r, $g, $b) - min($r, $g, $b) > 15 && 
                              abs($r - $g) > 15 && $r > $g && $r > $b);
                    
                    if ($isSkin) {
                        $skinPixels++;
                    }
                }
            }

            return ($skinPixels / $totalPixels) > 0.45;
        } catch (\Exception $e) {
            Log::warning("Skin Tone check failed: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Bridge for Local Python Script
     */
    private function runLocalPythonModel(string $scriptPath, string $imagePath): string
    {
        try {
            $pythonBinary = env('PYTHON_BINARY');
            if (empty($pythonBinary)) {
                $venvPath = base_path('venv/bin/python3');
                $pythonBinary = file_exists($venvPath) ? $venvPath : 'python3';
            }

            $process = new Process([$pythonBinary, $scriptPath, $imagePath]);
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error("Local AI script failed: " . $process->getErrorOutput());
                return 'ERROR';
            }

            return trim($process->getOutput());
        } catch (\Exception $e) {
            Log::error("Local AI script execution failed: " . $e->getMessage());
            return 'ERROR';
        }
    }

    private function runSightengineRawCheck(UploadedFile $file): ?array
    {
        $apiUser = config('services.sightengine.api_user');
        $apiSecret = config('services.sightengine.api_secret');

        if (empty($apiUser) || empty($apiSecret)) {
            return null;
        }

        try {
            // استخدام كافة الموديلات المطلوبة للنسخة الثالثة المتاحة مع اضافة text-content للبيانات الحساسة
            $url = 'https://api.sightengine.com/1.0/check.json?' . http_build_query([
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,alcohol,recreational_drug,medical,text-content',
            ]);

            $response = Http::timeout(40)
                ->when(app()->environment('local'), function ($http) {
                    return $http->withoutVerifying(); // حل مشكلة SSL في اللوكلي
                })
                ->attach('media', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
                ->post($url);

            return $response->successful() ? $response->json() : null;

        } catch (\Exception $e) {
            Log::error('Sightengine System Error: ' . $e->getMessage());
            return null;
        }
    }

    private function banHash(string $hash, string $reason, ?array $details = null): void
    {
        BannedImageHash::firstOrCreate(
            ['hash' => $hash],
            ['reason' => $reason, 'details' => $details]
        );
    }
}