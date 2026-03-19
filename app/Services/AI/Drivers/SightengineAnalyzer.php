<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\DTOs\AnalysisResult;
use App\Services\AI\DTOs\ImageAnalysisResult;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\AnalyzerException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SightengineAnalyzer implements MediaAnalyzerInterface
{
    public function getName(): string
    {
        return 'sightengine';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("SightengineAnalyzer only supports images currently in this failover implementation.");
        }

        $apiUser = config('services.sightengine.api_user');
        $apiSecret = config('services.sightengine.api_secret');

        if (empty($apiUser) || empty($apiSecret)) {
            throw new AnalyzerException("Sightengine credentials not configured.");
        }

        try {
            $path = $media->path;
            
            if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                $content = file_get_contents($media->url); 
            } else {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            }

            $response = Http::attach(
                'media', 
                $content, 
                $media->filename ?? 'image.jpg'
            )->post('https://api.sightengine.com/1.0/check.json', [
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,text-content,properties',
            ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    Log::channel('datadog')->error("Sightengine Quota Exceeded", ['driver' => $this->getName()]);
                    throw new QuotaExceededException("Sightengine Quota Exceeded.");
                }
                Log::channel('datadog')->error("Sightengine API Error", ['driver' => $this->getName(), 'status' => $response->status()]);
                throw new AnalyzerException("Sightengine API Error: " . $response->body());
            }

            $data = $response->json();
            
            if (($data['status'] ?? '') === 'failure') {
                if (($data['error']['code'] ?? '') == 429) {
                    Log::channel('datadog')->error("Sightengine Quota Exceeded (API)", ['driver' => $this->getName()]);
                    throw new QuotaExceededException("Sightengine Quota Exceeded via API response.");
                }
                Log::channel('datadog')->error("Sightengine Failure Response", ['driver' => $this->getName(), 'error' => $data['error']['message'] ?? 'Unknown error']);
                throw new AnalyzerException("Sightengine Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            // Extract tags from properties or other models if available
            $tags = [];
            if (isset($data['text']['profanity'])) {
                foreach ($data['text']['profanity'] as $p) $tags[] = $p['text'];
            }

            $isSensitive = ($data['nudity']['none'] ?? 1) < 0.5 || ($data['weapon'] ?? 0) > 0.5;

            Log::channel('datadog')->info("Sightengine Analysis Success", [
                'driver' => $this->getName(),
                'is_sensitive' => $isSensitive,
                'media_id' => $media->id ?? 'new',
            ]);

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $isSensitive
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::channel('datadog')->error("Sightengine Analysis Exception", [
                'driver' => $this->getName(),
                'message' => $e->getMessage(),
            ]);
            Log::error("Sightengine Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
