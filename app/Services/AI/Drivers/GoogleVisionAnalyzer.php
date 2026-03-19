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

class GoogleVisionAnalyzer implements MediaAnalyzerInterface
{
    public function getName(): string
    {
        return 'google_vision';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("GoogleVisionAnalyzer only supports images.");
        }

        $apiKey = config('services.google.vision_api_key');
        if (!$apiKey) {
            throw new AnalyzerException("Google Vision API Key not configured.");
        }

        try {
            // Fix: Google cannot reach local 127.0.0.1 URLs. We MUST send the file content directly.
            $path = $media->path; // Uses proxy accessor to ImageStorage->path
            
            if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                // If not local public, try fetching from the absolute URL or S3
                $content = file_get_contents($media->url); 
            } else {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            }

            $base64Image = base64_encode($content);

            $response = Http::post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [
                    [
                        'image' => ['content' => $base64Image],
                        'features' => [
                            ['type' => 'LABEL_DETECTION', 'maxResults' => 10],
                            ['type' => 'SAFE_SEARCH_DETECTION'],
                            ['type' => 'TEXT_DETECTION'],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Google Vision Quota Exceeded.");
                }
                throw new AnalyzerException("Google Vision API Error: " . $response->body());
            }

            $data = $response->json();
            $res = $data['responses'][0] ?? [];

            if (isset($res['error'])) {
                 throw new AnalyzerException("Google Vision API Error: " . ($res['error']['message'] ?? 'Unknown error'));
            }

            $tags = [];
            if (isset($res['labelAnnotations'])) {
                foreach ($res['labelAnnotations'] as $label) {
                    $tags[] = $label['description'];
                }
            }

            $ocrText = $res['fullTextAnnotation']['text'] ?? null;
            
            $safeSearch = $res['safeSearchAnnotation'] ?? [];
            $isSensitive = in_array($safeSearch['adult'] ?? '', ['LIKELY', 'VERY_LIKELY']) || 
                           in_array($safeSearch['violence'] ?? '', ['LIKELY', 'VERY_LIKELY']);

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $res,
                tags: $tags,
                ocrText: $ocrText,
                isSensitive: $isSensitive
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Google Vision Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
