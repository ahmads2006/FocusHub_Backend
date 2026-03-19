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
            $path = $media->path;
            if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                $content = file_get_contents($media->url); 
            } else {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            }

            $timestamp = time();
            // Request AI tags from AWS Rekognition add-on
            $paramsToSign = [
                'auto_tagging' => '0.6',
                'categorization' => 'aws_rek_tagging',
                'timestamp' => $timestamp
            ];
            
            ksort($paramsToSign);
            $strToSign = '';
            foreach ($paramsToSign as $k => $v) {
                $strToSign .= "{$k}={$v}&";
            }
            $strToSign = rtrim($strToSign, '&') . $apiSecret;
            $signature = sha1($strToSign);

            $response = Http::attach('file', $content, $media->filename ?? 'image.jpg')
                ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'api_key' => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'auto_tagging' => '0.6',
                    'categorization' => 'aws_rek_tagging',
                ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Cloudinary Quota Exceeded.");
                }
                throw new AnalyzerException("Cloudinary API Error: " . $response->body());
            }

            $data = $response->json();
            
            // Cloudinary standard tags returned upon upload if any rules or auto-tagging is enabled on the cloud
            $tags = $data['tags'] ?? [];
            if (isset($data['info']['categorization'])) {
                foreach ($data['info']['categorization'] as $engine => $result) {
                    foreach ($result['data'] ?? [] as $category) {
                        if (($category['confidence'] ?? 0) > 0.5) {
                            $tags[] = $category['tag'] ?? $category['name'] ?? '';
                        }
                    }
                }
            }

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: array_unique(array_filter($tags)),
                isSensitive: false // Cloudinary doesn't do native moderation on standard upload without add-ons
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Cloudinary Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
