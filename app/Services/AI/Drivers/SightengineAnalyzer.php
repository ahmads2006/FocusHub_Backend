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
            // Sightengine usually needs the file content or a URL. 
            // We'll use the URL if available, otherwise we'd need to fetch the file.
            $url = $media->getPublicUrl() ?? $media->url; // Assuming model has such method/field

            $response = Http::get('https://api.sightengine.com/1.0/check.json', [
                'api_user' => $apiUser,
                'api_secret' => $apiSecret,
                'url' => $url,
                'models' => 'nudity-2.1,weapon,offensive-2.0,gore-2.0,text-content,properties',
            ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Sightengine Quota Exceeded.");
                }
                throw new AnalyzerException("Sightengine API Error: " . $response->body());
            }

            $data = $response->json();
            
            if (($data['status'] ?? '') === 'failure') {
                if (($data['error']['code'] ?? '') == 429) {
                    throw new QuotaExceededException("Sightengine Quota Exceeded via API response.");
                }
                throw new AnalyzerException("Sightengine Error: " . ($data['error']['message'] ?? 'Unknown error'));
            }

            // Extract tags from properties or other models if available
            $tags = [];
            if (isset($data['text']['profanity'])) {
                foreach ($data['text']['profanity'] as $p) $tags[] = $p['text'];
            }

            $isSensitive = ($data['nudity']['none'] ?? 1) < 0.5 || ($data['weapon'] ?? 0) > 0.5;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $isSensitive
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Sightengine Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
