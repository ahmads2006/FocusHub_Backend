<?php

namespace App\Services\AI;

use App\Models\BannedImageHash;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ContentSafetyService
 *
 * All safety checks go through the MediaAnalyzerManager failover system.
 * No inline image processing (skin-tone heuristics, Python scripts, etc.).
 */
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

        // LAYER 1: Blacklist & Cache
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

        // LAYER 1 (Python Local Heuristics) REMOVED.
        // We rely completely on the robust Image AI Engine (Sightengine) in Layer 2
        // to avoid false positives and unnecesary command line execution overhead.

        // LAYER 2: Failover AI File Analysis (Sightengine -> Cloudinary)
        try {
            /** @var \App\Services\AI\Contracts\MediaAnalyzerInterface|MediaAnalyzerManager $analyzer */
            $analyzer = app(\App\Services\AI\Contracts\MediaAnalyzerInterface::class);
            
            Log::info("AI Safety: Running Layer 2 (Cloud AI) on {$fileHash}");
            $analysisResult = $analyzer->analyzeFile($file, 'image');
            
            // v15.0: Read the safety verdict from the analyzer (Sightengine Master Decision)
            $safetyVerdict = $analysisResult->rawResults['_safety_verdict'] ?? null;
            $sensitivityReasons = $analysisResult->rawResults['_sensitivity_reasons'] ?? [];
            
            if ($safetyVerdict) {
                // Sightengine (or compatible driver) provided a direct verdict
                $status = $safetyVerdict;
                $reason = match ($status) {
                    'rejected' => 'AI Rejected: ' . implode(', ', $sensitivityReasons),
                    'pending_review' => 'AI Flagged: ' . implode(', ', $sensitivityReasons),
                    default => 'Safe',
                };
            } else {
                // Fallback for drivers that don't provide _safety_verdict (e.g. Cloudinary)
                $status = $analysisResult->isSensitive ? 'pending_review' : 'approved';
                $reason = $analysisResult->isSensitive ? 'AI Flagged (Sensitive)' : 'Safe';
            }
            
            if ($status === 'pending_review') {
                Log::channel('datadog')->warning("Image Flagged (Sensitive Contents)", [
                    'hash' => $fileHash,
                    'driver' => $analysisResult->driverName,
                    'reasons' => $sensitivityReasons,
                ]);
            }
            
            if ($status === 'rejected') {
                Log::channel('datadog')->error("Image Rejected (Safety Verdict)", [
                    'hash' => $fileHash,
                    'driver' => $analysisResult->driverName,
                    'reasons' => $sensitivityReasons,
                    'gore_score' => $analysisResult->rawResults['_gore_score'] ?? 0,
                ]);
                $this->banHash($fileHash, "AI Rejected: {$analysisResult->driverName}", $analysisResult->rawResults);
            }

            $result = [
                'status' => $status,
                'is_sensitive' => in_array($status, ['pending_review', 'rejected']),
                'is_visible' => ($status !== 'rejected'),
                'reason' => $reason,
                'driver' => $analysisResult->driverName,
                'metadata' => array_merge($metadata, $analysisResult->toArray()),
            ];

            \Illuminate\Support\Facades\Cache::put("moderation_hash:{$fileHash}", $result, now()->addDays(7));
            return $result;

        } catch (\Exception $e) {
            Log::error("AI Safety Failover System Failed: " . $e->getMessage());
            // FAIL-CLOSED: If all engines fail, mark as pending_review (NOT approved)
            return ['status' => 'pending_review', 'is_sensitive' => true, 'is_visible' => true, 'reason' => 'Fail-Closed (All Engines Down)', 'driver' => 'none', 'metadata' => $metadata];
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