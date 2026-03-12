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
    /**
     * Validate the file and return the moderation result.
     * Throws exception ONLY if strictly rejected by policy.
     */
    public function validate(UploadedFile $file): array
    {
        $result = $this->check($file);

        // تعديل: الحظر فقط إذا كانت الحالة 'rejected' صراحة
        // صور 'pending_review' سيتم السماح بها حالياً لتسهيل تجربة المشروع
        if ($result['status'] === 'rejected') {
            throw ValidationException::withMessages([
                'image' => __('هذه الصورة تخالف سياسات الموقع لمكافحة المحتوى غير اللائق، وتم حظرها فوراً.'),
            ]);
        }

        return $result;
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

        // LAYER 2: Heuristics
        $hasSkinFlag = $this->hasExcessiveSkinTones($filePath);
        $metadata['checks']['skin_heuristic'] = $hasSkinFlag ? 'flagged' : 'pass';

        // LAYER 3: Local Python ML
        Log::info("AI Safety: Running Python ML...");
        $pythonScript = base_path('scripts/ai_filter.py');
        $pythonResult = 'skipped';
        if (file_exists($pythonScript)) {
            $pythonRaw = $this->runLocalPythonModel($pythonScript, $filePath);
            $pythonResult = $pythonRaw; 
        }
        $metadata['checks']['local_ml'] = $pythonResult;
        Log::info("AI Safety: Python ML Result: " . $pythonResult);

        // Auto-Ban if Local AI is UNSAFE
        if ($pythonResult === 'UNSAFE') {
            $this->banHash($fileHash, 'Local AI Filter Reject');
            return [
                'status' => 'rejected',
                'reason' => 'Local AI Reject (Security Policy)',
                'metadata' => $metadata
            ];
        }

        // LAYER 4: Sightengine AI
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

        // 4. Offensive & Gore
        $offensive = $sightengineRaw['offensive']['prob'] ?? 0;
        $gore = $sightengineRaw['gore']['prob'] ?? 0;

        // 5. Sensitive Data (PII)
        $hasSensitiveData = (
            ($sightengineRaw['phones']['prob'] ?? 0) > 0.5 ||
            ($sightengineRaw['links']['prob'] ?? 0) > 0.5 ||
            ($sightengineRaw['emails']['prob'] ?? 0) > 0.5
        );

        // Log the AI scores for transparency
        Log::info('Sightengine V3 scores for ' . $file->getClientOriginalName() . ': ' . json_encode([
            'safe_nudity' => $isSafeNudity,
            'max_weapon' => $maxWeapon,
            'gore' => $gore,
            'pii_detected' => $hasSensitiveData
        ]));

        // --- Decision Logic (Balanced Version 3) ---

        // A. Critical Rejection (Red Zone)
        // High confidence violations get immediate rejection and ban.
        $isRejected = ($isSafeNudity < 0.25) || // Very high nudity probability
                      ($sexualDisplay > 0.85) ||
                      ($maxWeapon > 0.80) ||
                      ($gore > 0.75) ||
                      ($drugs > 0.85) ||
                      ($offensive > 0.95);

        if ($isRejected) {
            $this->banHash($fileHash, 'Strict Policy Violation (V3)', $sightengineRaw);
            return [
                'status' => 'rejected', 
                'reason' => 'Violation detected (Strict Policy)', 
                'metadata' => $metadata
            ];
        }

        // B. Managed Review (Gray Zone)
        // Sensitive data, alcohol, or borderline cases go to review.
        $needsReview = (
            ($isSafeNudity >= 0.25 && $isSafeNudity <= 0.65) || 
            ($maxWeapon >= 0.35 && $maxWeapon <= 0.80) ||
            ($alcohol > 0.60) || 
            $hasSensitiveData ||
            $pythonResult === 'SUSPICIOUS'
        );

        if ($needsReview) {
            return [
                'status' => 'pending_review',
                'reason' => 'Sensitive content/data detected (V3)',
                'metadata' => $metadata
            ];
        }

        // C. Clean Approval (Green Zone)
        return [
            'status' => 'approved',
            'reason' => 'Safe (V3 Verified)',
            'metadata' => $metadata
        ];
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
            // استخدام كافة الموديلات المطلوبة للنسخة الثالثة (PII, Drugs, Alcohol, etc.)
            $url = 'https://api.sightengine.com/1.0/check.json?' . http_build_query([
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,alcohol,recreational_drug,medical,phones,links,emails',
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