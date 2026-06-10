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

class ImaggaAnalyzer implements MediaAnalyzerInterface
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
        return 'imagga';
    }

    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("ImaggaAnalyzer only supports images.");
        }

        $key = config('services.imagga.key');
        $secret = config('services.imagga.secret');
        $endpoint = config('services.imagga.endpoint', 'https://api.imagga.com/v2');

        if (!$key || !$secret) {
            throw new AnalyzerException("Imagga API Key/Secret not configured.");
        }

        try {
            $url = $media->getRawOriginal('url') ?? $media->url;
            $isPubliclyAccessible = $url && (str_starts_with($url, 'https://') || (str_starts_with($url, 'http://') && !str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')));

            if ($isPubliclyAccessible) {
                // Fetch tags using the image URL
                Log::info("{$this->getName()} Analyzer: Tagging via URL: {$url}");
                $response = Http::withBasicAuth($key, $secret)
                    ->connectTimeout(10)
                    ->timeout(20)
                    ->get("{$endpoint}/tags", [
                        'image_url' => $url
                    ]);
            } else {
                // Local / private file — upload the content directly
                $path = $media->storage->path ?? $media->path;
                if ($path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $content = \Illuminate\Support\Facades\Storage::disk('public')->get($path);
                } elseif ($path && \Illuminate\Support\Facades\Storage::disk('s3')->exists($path)) {
                    $content = \Illuminate\Support\Facades\Storage::disk('s3')->get($path);
                } else {
                    Log::warning("{$this->getName()} Analyzer: File not found locally. Falling back to fetch URL: {$url}");
                    $content = @file_get_contents($url);
                    if ($content === false) {
                        throw new AnalyzerException("Failed to read media content from URL/Path: {$url}");
                    }
                }

                $filename = $media->filename ?? 'image.jpg';
                Log::info("{$this->getName()} Analyzer: Tagging via direct upload: {$filename}");
                
                $response = Http::withBasicAuth($key, $secret)
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->attach('image', $content, $filename)
                    ->post("{$endpoint}/tags");
            }

            if ($response->failed()) {
                if ($response->status() === 429 || str_contains($response->body(), 'limit') || str_contains($response->body(), 'quota')) {
                    throw new QuotaExceededException("Imagga Quota Exceeded.", driverName: 'imagga');
                }
                throw new AnalyzerException("Imagga API Error: " . $response->body());
            }

            $data = $response->json();
            $tags = $this->extractTags($data);

            $safetyResult = $this->evaluateSafety($tags);
            $category = $this->deriveCategory($tags);
            $caption = $this->generateCaption($tags);

            // Inject safety verdict into raw results
            $data['_safety_verdict'] = $safetyResult['verdict'];
            $data['_sensitivity_reasons'] = $safetyResult['reasons'];
            $data['_gore_score'] = $safetyResult['gore_score'];

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $safetyResult['is_sensitive'],
                qualityGrade: 'high_quality',
                category: $category,
                caption: $caption,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Imagga Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException(message: $e->getMessage(), driverName: 'imagga', code: (int)$e->getCode(), previous: $e);
        }
    }

    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult
    {
        if ($mediaType !== 'image') {
            throw new AnalyzerException("ImaggaAnalyzer only supports images.");
        }

        $key = config('services.imagga.key');
        $secret = config('services.imagga.secret');
        $endpoint = config('services.imagga.endpoint', 'https://api.imagga.com/v2');

        if (!$key || !$secret) {
            throw new AnalyzerException("Imagga API Key/Secret not configured.");
        }

        try {
            $content = file_get_contents($file->getRealPath());
            if ($content === false) {
                throw new AnalyzerException("Failed to read uploaded file content.");
            }

            $filename = $file->getClientOriginalName() ?? 'image.jpg';
            Log::info("{$this->getName()} Analyzer (File): Tagging via direct upload: {$filename}");

            $response = Http::withBasicAuth($key, $secret)
                ->connectTimeout(10)
                ->timeout(30)
                ->attach('image', $content, $filename)
                ->post("{$endpoint}/tags");

            if ($response->failed()) {
                if ($response->status() === 429 || str_contains($response->body(), 'limit') || str_contains($response->body(), 'quota')) {
                    throw new QuotaExceededException("Imagga Quota Exceeded.", driverName: 'imagga');
                }
                throw new AnalyzerException("Imagga API Error: " . $response->body());
            }

            $data = $response->json();
            $tags = $this->extractTags($data);

            $safetyResult = $this->evaluateSafety($tags);
            $category = $this->deriveCategory($tags);
            $caption = $this->generateCaption($tags);

            $data['_safety_verdict'] = $safetyResult['verdict'];
            $data['_sensitivity_reasons'] = $safetyResult['reasons'];
            $data['_gore_score'] = $safetyResult['gore_score'];

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: $data,
                tags: $tags,
                isSensitive: $safetyResult['is_sensitive'],
                qualityGrade: 'high_quality',
                category: $category,
                caption: $caption,
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Imagga Analysis Exception (File): " . $e->getMessage());
            throw new AnalyzerException(message: $e->getMessage(), driverName: 'imagga', code: (int)$e->getCode(), previous: $e);
        }
    }

    public function analyzeTags(Model $media, string $mediaType = 'image'): AnalysisResult
    {
        return $this->analyze($media, $mediaType);
    }

    /**
     * Extract tags from Imagga's response structure.
     */
    protected function extractTags(array $data): array
    {
        $tags = [];
        $rawTags = $data['result']['tags'] ?? [];

        foreach ($rawTags as $tagItem) {
            $confidence = $tagItem['confidence'] ?? 0;
            // Only keep tags with > 40% confidence
            if ($confidence > 40 && isset($tagItem['tag']['en'])) {
                $tags[] = $tagItem['tag']['en'];
            }
        }

        return array_unique($tags);
    }

    /**
     * Evaluate tag-based content safety (moderation keywords).
     */
    protected function evaluateSafety(array $tags): array
    {
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

        return [
            'is_sensitive' => $isSensitive,
            'verdict'      => $safetyVerdict,
            'gore_score'   => $goreScore,
            'reasons'      => $sensitivityReasons,
        ];
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

    /**
     * Generate a caption from top tags.
     */
    protected function generateCaption(array $tags): ?string
    {
        if (!empty($tags)) {
            $topTags = array_slice(array_values(array_unique($tags)), 0, 3);
            return 'Image of ' . implode(', ', $topTags);
        }

        return null;
    }
}
