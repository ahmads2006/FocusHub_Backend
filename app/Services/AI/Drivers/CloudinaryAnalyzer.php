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

class CloudinaryAnalyzer implements MediaAnalyzerInterface
{
    public function getName(): string
    {
        return 'cloudinary';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        // Cloudinary requires its own SDK or specific API calls for tagging/AI.
        // For brevity in this failover example, we simulate a Cloudinary AI tagging call.
        
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (!$cloudName || !$apiKey) {
            throw new AnalyzerException("Cloudinary credentials not configured.");
        }

        try {
            $publicId = $media->cloudinary_public_id ?? $media->id;
            
            // This is a conceptual API call for Cloudinary Google Tagging or Amazon Rekognition
            // URL format: https://<api_key>:<api_secret>@api.cloudinary.com/v1_1/<cloud_name>/resources/image/upload/<public_id>?tags=true
            $response = Http::withBasicAuth($apiKey, $apiSecret)
                ->get("https://api.cloudinary.com/v1_1/{$cloudName}/resources/image/upload/{$publicId}", [
                    'image_metadata' => true,
                    'colors' => true,
                ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Cloudinary Quota Exceeded.");
                }
                throw new AnalyzerException("Cloudinary API Error: " . $response->body());
            }

            $data = $response->json();
            
            // Note: Cloudinary returns tags if 'google_tagging' or similar was enabled on upload.
            $tags = $data['tags'] ?? [];

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: false // Simple simulation
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Cloudinary Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
