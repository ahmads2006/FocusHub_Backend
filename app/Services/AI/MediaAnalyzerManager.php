<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\Drivers\ImageKitAnalyzer;
use App\Services\AI\Drivers\SightengineAnalyzer;
use App\Services\AI\Drivers\GoogleVisionAnalyzer;
use App\Services\AI\Drivers\CloudinaryAnalyzer;
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
    protected array $failoverOrder = ['imagekit', 'sightengine', 'google_vision', 'cloudinary'];

    public function getDefaultDriver()
    {
        return $this->config->get('ai.analyzer.default', 'imagekit');
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

        foreach ($this->failoverOrder as $driverName) {
            // 1. Check if driver is known to be depleted (Quota Exceeded Cache)
            if (Cache::has("ai_quota_depleted:{$driverName}")) {
                Log::warning("AI Failover: Skipping {$driverName} (Quota known to be depleted).");
                continue;
            }

            try {
                Log::info("AI Failover: Attempting analysis with {$driverName}...");
                
                $driver = $this->driver($driverName);
                $result = $driver->analyze($media, $mediaType);

                Log::info("AI Failover: SUCCESS with {$driverName}.");
                return $result;

            } catch (QuotaExceededException $e) {
                Log::error("AI Failover: {$driverName} Quota Exceeded. Marking as depleted.");
                // Cache depletion for 1 hour to avoid hits
                Cache::put("ai_quota_depleted:{$driverName}", true, now()->addHour());
                $errors[$driverName] = $e->getMessage();
            } catch (AnalyzerException $e) {
                Log::error("AI Failover: {$driverName} Failed: " . $e->getMessage());
                $errors[$driverName] = $e->getMessage();
            } catch (\Exception $e) {
                Log::error("AI Failover: Unexpected error in {$driverName}: " . $e->getMessage());
                $errors[$driverName] = $e->getMessage();
            }
        }

        // If we reach here, all drivers failed
        throw new AllAnalyzersFailedException(
            "All AI Analyzers failed. Errors: " . json_encode($errors)
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
}
