<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\DTOs\AnalysisResult;
use App\Services\AI\DTOs\ImageAnalysisResult;
use App\Services\AI\Exceptions\QuotaExceededException;
use App\Services\AI\Exceptions\AnalyzerException;
use ImageKit\ImageKit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ImageKitAnalyzer implements MediaAnalyzerInterface
{
    protected ImageKit $client;

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

    public function __construct()
    {
        $this->client = new ImageKit(
            config('services.imagekit.public_key'),
            config('services.imagekit.private_key'),
            config('services.imagekit.url_endpoint')
        );
    }

    public function getName(): string
    {
        return 'imagekit';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("ImageKitAnalyzer only supports images currently.");
        }

        try {
            $fileId = $media->ik_file_id ?? $media->file_id;
            
            if (!$fileId) {
                throw new AnalyzerException("ImageKit File ID not found for media.");
            }

            $response = $this->client->getFileDetails($fileId);

            if ($response->err) {
                if ($response->err->raw['status'] == 429) {
                    throw new QuotaExceededException("ImageKit Quota Exceeded.");
                }
                throw new AnalyzerException("ImageKit API Error: " . json_encode($response->err));
            }

            $details = $response->success;
            $tags = $details->tags ?? [];
            $aiTags = [];
            
            // ImageKit stores AI tags in extension results if enabled
            if (isset($details->embeddedMetadata['ImageKit.AI.Tags'])) {
                $aiTags = $details->embeddedMetadata['ImageKit.AI.Tags'];
            }

            // Also check for AI extension results (Google Cloud Vision, etc.)
            if (isset($details->extensionStatus)) {
                foreach ($details->extensionStatus as $ext) {
                    if (isset($ext['result']['tags'])) {
                        $aiTags = array_merge($aiTags, $ext['result']['tags']);
                    }
                }
            }

            $allTags = array_merge($tags, $aiTags);

            // --- Sensitivity check via moderation keywords ---
            $isSensitive = false;
            $safetyVerdict = 'approved';
            $goreScore = 0.0;
            $sensitivityReasons = [];

            $hardRejectKeywords = ['weapon', 'execution', 'injury', 'blood', 'gore', 'violence'];
            $pendingKeywords = ['adult', 'suggestive', 'racy'];

            foreach ($allTags as $tag) {
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

            // --- Quality Grade (heuristic: use resolution + file size) ---
            $qualityGrade = $this->deriveQualityGrade($details);

            // --- Scene Category ---
            $category = $this->deriveCategory($allTags);

            $rawResults = (array) $details;
            
            // Inject standardized safety metrics into raw results
            $rawResults['_safety_verdict'] = $safetyVerdict;
            $rawResults['_sensitivity_reasons'] = $sensitivityReasons;
            $rawResults['_gore_score'] = $goreScore;

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $rawResults,
                tags: $allTags,
                isSensitive: $isSensitive,
                qualityGrade: $qualityGrade,
                category: $category,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("ImageKit Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        throw new AnalyzerException("ImageKitAnalyzer does not support raw file analysis (requires uploading to ImageKit first).");
    }

    /**
     * Derive quality grade from ImageKit file metadata.
     * Uses resolution and file size as heuristics.
     */
    protected function deriveQualityGrade($details): string
    {
        $width = $details->width ?? 0;
        $height = $details->height ?? 0;
        $size = $details->size ?? 0;

        // Very small images are likely low quality
        if ($width < 200 || $height < 200) {
            return 'low_quality';
        }

        // Small images with tiny file sizes suggest heavy compression
        if ($width < 800 && $height < 800 && $size < 50000) {
            return 'medium_quality';
        }

        // Check for very heavily compressed images (size/pixel ratio)
        $totalPixels = $width * $height;
        if ($totalPixels > 0) {
            $bytesPerPixel = $size / $totalPixels;
            if ($bytesPerPixel < 0.05) {
                return 'low_quality';
            }
            if ($bytesPerPixel < 0.15) {
                return 'medium_quality';
            }
        }

        return 'high_quality';
    }

    /**
     * Derive scene category from tags.
     */
    protected function deriveCategory(array $tags): string
    {
        $lowerTags = array_map('strtolower', array_filter($tags));

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

    public function analyzeTags(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        return $this->analyze($media, $mediaType);
    }
}
