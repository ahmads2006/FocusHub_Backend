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
    /**
     * Map of label keywords → scene categories.
     */
    protected const CATEGORY_MAP = [
        'forest'       => ['forest', 'woodland', 'jungle', 'tree', 'trees', 'rainforest'],
        'sea'          => ['sea', 'ocean', 'beach', 'coast', 'wave', 'coral', 'underwater'],
        'nature'       => ['nature', 'landscape', 'mountain', 'valley', 'river', 'lake', 'waterfall', 'sunset', 'sunrise', 'sky', 'cloud', 'field', 'meadow', 'garden', 'flower', 'plant'],
        'urban'        => ['city', 'urban', 'street', 'road', 'traffic', 'skyline', 'downtown', 'night city'],
        'architecture' => ['building', 'architecture', 'bridge', 'tower', 'church', 'mosque', 'cathedral', 'monument', 'castle', 'house', 'interior design'],
        'portrait'     => ['person', 'face', 'portrait', 'selfie', 'people', 'man', 'woman', 'child', 'smile'],
        'food'         => ['food', 'meal', 'dish', 'cuisine', 'dessert', 'fruit', 'vegetable', 'drink', 'coffee', 'cake'],
        'abstract'     => ['abstract', 'pattern', 'texture', 'art', 'painting', 'design', 'geometric'],
    ];

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

            $base64Image = base64_encode($content);

            $response = Http::post("https://vision.googleapis.com/v1/images:annotate?key={$apiKey}", [
                'requests' => [
                    [
                        'image' => ['content' => $base64Image],
                        'features' => [
                            ['type' => 'LABEL_DETECTION', 'maxResults' => 15],
                            ['type' => 'SAFE_SEARCH_DETECTION'],
                            ['type' => 'TEXT_DETECTION'],
                            ['type' => 'IMAGE_PROPERTIES'],
                            ['type' => 'CROP_HINTS'],
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
            
            $safetyVerdict = 'approved';
            $goreScore = 0.0;
            $sensitivityReasons = [];

            // Score Mapping Helper
            $getScore = function($likelihood) {
                if ($likelihood === 'VERY_LIKELY') return 0.95;
                if ($likelihood === 'LIKELY') return 0.7;
                if ($likelihood === 'POSSIBLE') return 0.4;
                return 0.0;
            };

            $isLikely = function($likelihood) {
                return in_array($likelihood, ['LIKELY', 'VERY_LIKELY'], true);
            };

            $violenceScore = $getScore($safeSearch['violence'] ?? '');
            if ($violenceScore > 0) {
                $goreScore = max($goreScore, $violenceScore);
            }

            if ($isLikely($safeSearch['violence'] ?? '')) {
                $safetyVerdict = 'rejected';
                $sensitivityReasons[] = 'violence';
            }
            if ($isLikely($safeSearch['adult'] ?? '')) {
                $safetyVerdict = 'rejected';
                $sensitivityReasons[] = 'adult';
            }

            // Yellow layer fallbacks if not already rejected
            if ($safetyVerdict !== 'rejected') {
                if ($isLikely($safeSearch['medical'] ?? '')) {
                    $safetyVerdict = 'pending_review';
                    $sensitivityReasons[] = 'medical';
                }
                if ($isLikely($safeSearch['racy'] ?? '')) {
                    $safetyVerdict = 'pending_review';
                    $sensitivityReasons[] = 'racy';
                }
                if ($isLikely($safeSearch['spoof'] ?? '')) {
                    $safetyVerdict = 'pending_review';
                    $sensitivityReasons[] = 'spoof';
                }
            }

            $isSensitive = ($safetyVerdict === 'rejected' || $safetyVerdict === 'pending_review');

            // --- Quality Grade ---
            $qualityGrade = $this->deriveQualityGrade($res);

            // --- Scene Category ---
            $category = $this->deriveCategory($tags);

            // Inject standardized safety metrics into raw results
            $res['_safety_verdict'] = $safetyVerdict;
            $res['_sensitivity_reasons'] = $sensitivityReasons;
            $res['_gore_score'] = $goreScore;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $res,
                tags: $tags,
                ocrText: $ocrText,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Google Vision Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), 0, $e);
        }
    }

    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        throw new AnalyzerException("GoogleVisionAnalyzer does not support raw file analysis without saving due to credential scoping. Use Sightengine/Cloudinary for pre-upload.");
    }

    /**
     * Derive quality grade from IMAGE_PROPERTIES and CROP_HINTS confidence.
     */
    protected function deriveQualityGrade(array $res): string
    {
        // Use cropHints confidence as a proxy for image sharpness/quality
        $cropHints = $res['cropHintsAnnotation']['cropHints'] ?? [];
        if (!empty($cropHints)) {
            $maxConfidence = 0;
            foreach ($cropHints as $hint) {
                $maxConfidence = max($maxConfidence, $hint['confidence'] ?? 0);
            }
            if ($maxConfidence < 0.5) {
                return 'low_quality';
            }
            if ($maxConfidence < 0.8) {
                return 'medium_quality';
            }
        }

        // Use dominant colors — very low-contrast images (few dominant colors) suggest low quality
        $dominantColors = $res['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [];
        if (!empty($dominantColors)) {
            $topScore = $dominantColors[0]['score'] ?? 0;
            // If a single color dominates > 80%, it's likely a very flat/corrupted image
            if ($topScore > 0.8 && count($dominantColors) <= 2) {
                return 'low_quality';
            }
        }

        return 'high_quality';
    }

    /**
     * Derive scene category from detected labels.
     */
    protected function deriveCategory(array $tags): ?string
    {
        $lowerTags = array_map('strtolower', $tags);

        foreach (self::CATEGORY_MAP as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (in_array($keyword, $lowerTags, true)) {
                    return $category;
                }
                // Partial match for compound labels like "Palm tree"
                foreach ($lowerTags as $tag) {
                    if (str_contains($tag, $keyword)) {
                        return $category;
                    }
                }
            }
        }

        return 'other';
    }
}
