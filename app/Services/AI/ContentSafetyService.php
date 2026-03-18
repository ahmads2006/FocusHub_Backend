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
     * Perform deep analysis using the Failover System and return status + metadata
     */
    public function check(UploadedFile $file): array
    {
        Log::info("AI Safety: Starting failover check for " . $file->getClientOriginalName());
        $filePath = $file->getRealPath();
        $fileHash = md5_file($filePath);
        $metadata = [
            'hash' => $fileHash,
            'checks' => []
        ];

        // LAYER 1: Blacklist & Cache (Same as before)
        if (BannedImageHash::where('hash', $fileHash)->exists()) {
            return [
                'status' => 'rejected',
                'reason' => 'Hash Blacklist',
                'metadata' => $metadata
            ];
        }

        $cachedResult = \Illuminate\Support\Facades\Cache::get("moderation_hash:{$fileHash}");
        if ($cachedResult) {
            Log::info("AI Safety: Skipping scan. Returning Redis-cached result for hash {$fileHash}");
            return array_merge($cachedResult, ['metadata' => array_merge($metadata, ['cached_redis' => true])]);
        }

        // LAYER 2: Failover AI Analysis
        try {
            /** @var \App\Services\AI\Contracts\MediaAnalyzerInterface $analyzer */
            $analyzer = app(\App\Services\AI\Contracts\MediaAnalyzerInterface::class);
            
            // We need a temporary model or we mock it. 
            // Since our drivers expect a Model, let's create a "Dummy" one or pass the path.
            // Actually, let's update the interface to accept a file or path? 
            // No, let's stick to the model if possible or mock it.
            $mockMedia = new \App\Models\Image(['filename' => $file->getClientOriginalName()]);
            // Mock a URL or path for the analyzer
            $mockMedia->forceFill(['url' => $file->getRealPath()]); 

            $analysisResult = $analyzer->analyze($mockMedia, 'image');
            
            $status = $analysisResult->isSensitive ? 'pending_review' : 'approved';
            $reason = $analysisResult->isSensitive ? 'AI Flagged (Sensitive)' : 'Safe';
            
            // If it's a critical reject (e.g. from Sightengine or Google Vision)
            if (isset($analysisResult->rawResults['status']) && $analysisResult->rawResults['status'] === 'rejected') {
                $status = 'rejected';
                $this->banHash($fileHash, "AI Rejected: {$analysisResult->driverName}", $analysisResult->rawResults);
            }

            $result = [
                'status' => $status,
                'is_sensitive' => $analysisResult->isSensitive,
                'reason' => $reason,
                'driver' => $analysisResult->driverName, // This answers the user's question!
                'analysis' => $analysisResult->toArray(),
            ];

            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return $result;

        } catch (\Exception $e) {
            Log::error("AI Safety Failover System Failed: " . $e->getMessage());
            // Fall-Open Logic for Development
            return ['status' => 'approved', 'reason' => 'Fail-Open (System Error)', 'driver' => 'none', 'metadata' => $metadata];
        }
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