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
    /**
     * Map of tag keywords → scene categories.
     */
    protected const CATEGORY_MAP = [
        'forest'       => ['forest', 'woodland', 'jungle', 'tree', 'trees', 'rainforest'],
        'sea'          => ['sea', 'ocean', 'beach', 'coast', 'wave', 'coral', 'underwater'],
        'nature'       => ['nature', 'landscape', 'mountain', 'valley', 'river', 'lake', 'waterfall', 'sunset', 'sunrise', 'sky', 'cloud', 'field', 'meadow', 'garden', 'flower', 'plant'],
        'urban'        => ['city', 'urban', 'street', 'road', 'traffic', 'skyline', 'downtown'],
        'architecture' => ['building', 'architecture', 'bridge', 'tower', 'church', 'mosque', 'cathedral', 'monument', 'castle', 'house'],
        'portrait'     => ['person', 'face', 'portrait', 'selfie', 'people', 'man', 'woman', 'child'],
        'food'         => ['food', 'meal', 'dish', 'cuisine', 'dessert', 'fruit', 'vegetable', 'drink', 'coffee'],
        'abstract'     => ['abstract', 'pattern', 'texture', 'art', 'painting', 'design', 'geometric'],
    ];

    public function getName(): string
    {
        return 'cloudinary';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (!$cloudName || !$apiKey) {
            throw new AnalyzerException("Cloudinary credentials not configured.");
        }

        try {
            $path = $media->path;
            if (!$path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                // If it's a temp file path (pre-upload) or a full URL
                $url = $media->getRawOriginal('url') ?? $media->url;
                $content = @file_get_contents($url);
                if ($content === false) {
                    throw new AnalyzerException("Failed to read media content from URL/Path: {$url}");
                }
            } else {
                $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
            }

            $timestamp = time();
            $paramsToSign = [
                'auto_tagging' => '0.6',
                'categorization' => 'aws_rek_tagging',
                'quality_analysis' => 'true',
                'timestamp' => $timestamp,
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
                    'quality_analysis' => 'true',
                ]);

            if ($response->failed()) {
                if ($response->status() === 429) {
                    throw new QuotaExceededException("Cloudinary Quota Exceeded.");
                }
                throw new AnalyzerException("Cloudinary API Error: " . $response->body());
            }

            $data = $response->json();
            
            $tags = $data['tags'] ?? [];
            if (isset($data['info']['categorization'])) {
                foreach ($data['info']['categorization'] as $engine => $result) {
                    foreach ($result['data'] ?? [] as $cat) {
                        if (($cat['confidence'] ?? 0) > 0.5) {
                            $tags[] = $cat['tag'] ?? $cat['name'] ?? '';
                        }
                    }
                }
            }

            // --- Quality Grade from Cloudinary quality_analysis ---
            $qualityGrade = 'high_quality';
            $qualityScore = $data['quality_analysis']['focus'] ?? $data['quality_score'] ?? null;
            if ($qualityScore !== null) {
                if ($qualityScore < 0.4) {
                    $qualityGrade = 'low_quality';
                } elseif ($qualityScore < 0.7) {
                    $qualityGrade = 'medium_quality';
                }
            }

            // --- Scene Category ---
            $filteredTags = array_unique(array_filter($tags));
            $category = $this->deriveCategory($filteredTags);

            // --- Sensitivity check via moderation keywords ---
            $isSensitive = false;
            $safetyVerdict = 'approved';
            $goreScore = 0.0;
            $sensitivityReasons = [];

            $hardRejectKeywords = ['weapon', 'execution', 'injury', 'blood', 'gore', 'violence'];
            $pendingKeywords = ['adult', 'suggestive', 'racy'];

            foreach ($filteredTags as $tag) {
                $lowerTag = strtolower($tag);

                foreach ($hardRejectKeywords as $keyword) {
                    if (str_contains($lowerTag, $keyword)) {
                        $safetyVerdict = 'rejected';
                        $goreScore = max($goreScore, 0.9);
                        if (!in_array($keyword, $sensitivityReasons)) {
                            $sensitivityReasons[] = $keyword;
                        }
                    }
                }

                foreach ($pendingKeywords as $keyword) {
                    if (str_contains($lowerTag, $keyword)) {
                        if ($safetyVerdict !== 'rejected') {
                            $safetyVerdict = 'pending_review';
                        }
                        if (!in_array($keyword, $sensitivityReasons)) {
                            $sensitivityReasons[] = $keyword;
                        }
                    }
                }
            }

            $isSensitive = ($safetyVerdict === 'rejected' || $safetyVerdict === 'pending_review');

            // Inject standardized safety metrics into raw results
            $data['_safety_verdict'] = $safetyVerdict;
            $data['_sensitivity_reasons'] = $sensitivityReasons;
            $data['_gore_score'] = $goreScore;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $filteredTags,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Cloudinary Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("CloudinaryAnalyzer only supports images currently.");
        }

        try {
            $cloudName = config('services.cloudinary.cloud_name');
            $apiKey = config('services.cloudinary.api_key');
            $apiSecret = config('services.cloudinary.api_secret');

            if (!$cloudName || !$apiKey) {
                throw new AnalyzerException("Cloudinary credentials not configured.");
            }

            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new AnalyzerException("Failed to read uploaded file content.");
            }

            $timestamp = time();
            $paramsToSign = [
                'auto_tagging' => '0.6',
                'categorization' => 'aws_rek_tagging',
                'quality_analysis' => 'true',
                'timestamp' => $timestamp,
            ];
            
            ksort($paramsToSign);
            $strToSign = '';
            foreach ($paramsToSign as $k => $v) {
                $strToSign .= "{$k}={$v}&";
            }
            $strToSign = rtrim($strToSign, '&') . $apiSecret;
            $signature = sha1($strToSign);

            $response = Http::attach('file', $content, $file->getClientOriginalName() ?? 'image.jpg')
                ->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
                    'api_key' => $apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                    'auto_tagging' => '0.6',
                    'categorization' => 'aws_rek_tagging',
                    'quality_analysis' => 'true',
                ]);

            if ($response->failed()) {
                throw new AnalyzerException("Cloudinary API Error (File): " . $response->body());
            }

            $data = $response->json();
            $categories = $response['info']['categorization']['aws_rek_tagging']['data'] ?? [];
            foreach ($categories as $cat) {
                $tags[] = $cat['tag'];
            }

            // --- Sensitivity check via moderation keywords ---
            $isSensitive = false;
            $safetyVerdict = 'approved';
            $goreScore = 0.0;
            $sensitivityReasons = [];

            $hardRejectKeywords = ['weapon', 'execution', 'injury', 'blood', 'gore', 'violence'];
            $pendingKeywords = ['adult', 'suggestive', 'racy'];

            foreach ($tags as $tag) {
                $lowerTag = strtolower($tag);

                foreach ($hardRejectKeywords as $keyword) {
                    if (str_contains($lowerTag, $keyword)) {
                        $safetyVerdict = 'rejected';
                        $goreScore = max($goreScore, 0.9);
                        if (!in_array($keyword, $sensitivityReasons)) {
                            $sensitivityReasons[] = $keyword;
                        }
                    }
                }

                foreach ($pendingKeywords as $keyword) {
                    if (str_contains($lowerTag, $keyword)) {
                        if ($safetyVerdict !== 'rejected') {
                            $safetyVerdict = 'pending_review';
                        }
                        if (!in_array($keyword, $sensitivityReasons)) {
                            $sensitivityReasons[] = $keyword;
                        }
                    }
                }
            }

            $isSensitive = ($safetyVerdict === 'rejected' || $safetyVerdict === 'pending_review');

            $qualityGrade = 'high_quality';
            if (isset($response['quality_analysis']['focus'])) {
                $focus = $response['quality_analysis']['focus'];
                if ($focus < 0.3) {
                    $qualityGrade = 'low_quality';
                } elseif ($focus < 0.6) {
                    $qualityGrade = 'medium_quality';
                }
            }

            $category = $this->deriveCategory($tags);

            // Clean up: delete immediately, we only needed analysis
            if (isset($response['public_id'])) {
                try {
                    $this->cloudinary->uploadApi()->destroy($response['public_id']);
                } catch (\Exception $e) {
                    // Ignore deletion errors
                }
            }

            // Inject standardized safety metrics into raw results
            $response['_safety_verdict'] = $safetyVerdict;
            $response['_sensitivity_reasons'] = $sensitivityReasons;
            $response['_gore_score'] = $goreScore;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $response,
                tags: $tags,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Cloudinary Analysis Exception (File): " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Derive scene category from tags.
     */
    protected function deriveCategory(array $tags): string
    {
        $lowerTags = array_map('strtolower', $tags);

        foreach (self::CATEGORY_MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                foreach ($lowerTags as $tag) {
                    if ($tag === $keyword || str_contains($tag, $keyword)) {
                        return $category;
                    }
                }
            }
        }

        return 'other';
    }
}
