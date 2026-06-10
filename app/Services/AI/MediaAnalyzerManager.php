<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\Drivers\ImageKitAnalyzer;
use App\Services\AI\Drivers\SightengineAnalyzer;
use App\Services\AI\Drivers\GoogleVisionAnalyzer;
use App\Services\AI\Drivers\CloudinaryAnalyzer;
use App\Services\AI\Drivers\ImaggaAnalyzer;
use App\Services\AI\Exceptions\AllAnalyzersFailedException;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\AnalyzerException;
use App\Services\AI\DTOs\AnalysisResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;

class MediaAnalyzerManager extends Manager implements MediaAnalyzerInterface
{
    /**
     * The order of failover.
     */
    protected array $failoverOrder = ['sightengine', 'cloudinary', 'google_vision', 'imagga', 'imagekit'];

    public function getDefaultDriver()
    {
        return $this->config->get('ai.analyzer.default', 'sightengine');
    }

    public function getName(): string
    {
        return 'manager';
    }

    /**
     * Core Failover Logic: Attempt drivers in order until one succeeds.
     */
    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        $errors = [];
        $lastFailedDriver = null;

        foreach ($this->failoverOrder as $driverName) {
            // 1. Check if driver is known to be depleted (Quota Exceeded Cache)
            if (Cache::has("ai_quota_depleted:{$driverName}")) {
                Log::warning("AI Failover: Skipping {$driverName} (Quota known to be depleted).");
                continue;
            }

            try {
                if ($lastFailedDriver) {
                    Log::channel('datadog')->warning("AI Failover Event", [
                        'failed_service' => $lastFailedDriver,
                        'current_service' => $driverName,
                        'media_id' => $media->id,
                    ]);
                }

                Log::info("AI Failover: Attempting analysis with {$driverName}...");
                
                $driver = $this->driver($driverName);
                $result = $driver->analyze($media, $mediaType);

                Log::info("AI Failover: SUCCESS with {$driverName}.");
                return $result;

            } catch (QuotaExceededException $e) {
                Log::error("AI Failover: {$driverName} Quota Exceeded. Marking as depleted.");
                $ttl = $e->retryAfterSeconds ? now()->addSeconds($e->retryAfterSeconds) : now()->addHours(5);
                Cache::put("ai_quota_depleted:{$driverName}", true, $ttl);
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (AnalyzerException $e) {
                Log::error("AI Failover: {$driverName} Failed: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (\Exception $e) {
                Log::error("AI Failover: Unexpected error in {$driverName}: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            }
        }

        // If we reach here, all drivers failed
        throw new AllAnalyzersFailedException(
            'All AI Analyzers failed for media analysis.',
            driverErrors: $errors
        );
    }

    /**
     * Core Failover Logic for Raw Files (Pre-Upload Validation).
     * ONLY uses drivers that support raw file analysis (Sightengine, Cloudinary).
     */
    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        $errors = [];
        $lastFailedDriver = null;
        
        // For pre-upload, we only use these two
        $rawFileDrivers = ['sightengine', 'cloudinary'];

        foreach ($rawFileDrivers as $driverName) {
            if (Cache::has("ai_quota_depleted:{$driverName}")) {
                Log::warning("AI Failover (File): Skipping {$driverName} (Quota known to be depleted).");
                continue;
            }

            try {
                if ($lastFailedDriver) {
                    Log::channel('datadog')->warning("AI Failover Event (File)", [
                        'failed_service' => $lastFailedDriver,
                        'current_service' => $driverName,
                    ]);
                }

                Log::info("AI Failover (File): Attempting analysis with {$driverName}...");
                
                $driver = $this->driver($driverName);
                $result = $driver->analyzeFile($file, $mediaType);

                Log::info("AI Failover (File): SUCCESS with {$driverName}.");
                return $result;

            } catch (QuotaExceededException $e) {
                Log::error("AI Failover (File): {$driverName} Quota Exceeded. Marking as depleted.");
                $ttl = $e->retryAfterSeconds ? now()->addSeconds($e->retryAfterSeconds) : now()->addHours(5);
                Cache::put("ai_quota_depleted:{$driverName}", true, $ttl);
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (AnalyzerException $e) {
                Log::error("AI Failover (File): {$driverName} Failed: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (\Exception $e) {
                Log::error("AI Failover (File): Unexpected error in {$driverName}: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            }
        }

        throw new AllAnalyzersFailedException(
            'All AI File Analyzers failed for pre-upload validation.',
            driverErrors: $errors
        );
    }

    /**
     * Stage 2: Intelligence & Tagging ONLY (No Safety Re-scan).
     * Uses Google Vision → Cloudinary failover for extracting tags/labels/categories.
     * This method should only be called AFTER safety has been confirmed (Stage 1).
     */
    public function analyzeTags(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        $errors = [];
        $lastFailedDriver = null;

        // Tagging-only drivers (Google Vision first, Cloudinary as fallback, Imagga as tertiary fallback)
        $taggingDrivers = ['google_vision', 'cloudinary', 'imagga'];

        foreach ($taggingDrivers as $driverName) {
            if (Cache::has("ai_quota_depleted:{$driverName}")) {
                Log::warning("AI Pipeline (Tags): Skipping {$driverName} (Quota known to be depleted).");
                continue;
            }

            try {
                if ($lastFailedDriver) {
                    Log::channel('datadog')->warning("AI Pipeline (Tags) Failover", [
                        'failed_service' => $lastFailedDriver,
                        'current_service' => $driverName,
                        'media_id' => $media->id,
                    ]);
                }

                Log::info("AI Pipeline (Tags): Attempting tagging with {$driverName}...");
                
                $driver = $this->driver($driverName);
                $result = $driver->analyze($media, $mediaType);

                Log::info("AI Pipeline (Tags): SUCCESS with {$driverName}.");
                return $result;

            } catch (QuotaExceededException $e) {
                Log::error("AI Pipeline (Tags): {$driverName} Quota Exceeded. Marking as depleted.");
                $ttl = $e->retryAfterSeconds ? now()->addSeconds($e->retryAfterSeconds) : now()->addHours(5);
                Cache::put("ai_quota_depleted:{$driverName}", true, $ttl);
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (AnalyzerException $e) {
                Log::error("AI Pipeline (Tags): {$driverName} Failed: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            } catch (\Exception $e) {
                Log::error("AI Pipeline (Tags): Unexpected error in {$driverName}: " . $e->getMessage());
                $errors[$driverName] = $e;
                $lastFailedDriver = $driverName;
            }
        }

        throw new AllAnalyzersFailedException(
            'All AI Tagging Analyzers failed.',
            driverErrors: $errors
        );
    }

    // --- Driver Creation ---

    public function createImagekitDriver(): ImageKitAnalyzer
    {
        return new ImageKitAnalyzer();
    }

    public function createSightengineDriver(): SightengineAnalyzer
    {
        return new SightengineAnalyzer();
    }

    public function createGoogleVisionDriver(): GoogleVisionAnalyzer
    {
        return new GoogleVisionAnalyzer();
    }

    public function createCloudinaryDriver(): CloudinaryAnalyzer
    {
        return new CloudinaryAnalyzer();
    }

    public function createImaggaDriver(): ImaggaAnalyzer
    {
        return new ImaggaAnalyzer();
    }
}
