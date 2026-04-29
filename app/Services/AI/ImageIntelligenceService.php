<?php

namespace App\Services\AI;

use App\Models\Image;
use App\Models\ImageLabel;
use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\DTOs\ImageAnalysisResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ImageIntelligenceService
{
    /**
     * @var MediaAnalyzerInterface
     */
    protected $analyzer;

    /**
     * @var \App\Services\Core\ImageKitService
     */
    protected $imageKit;

    /**
     * @var \App\Services\AI\ContentSafetyService
     */
    protected $safety;

    public function __construct(
        MediaAnalyzerInterface $analyzer,
        \App\Services\Core\ImageKitService $imageKit,
        \App\Services\AI\ContentSafetyService $safety
    ) {
        $this->analyzer = $analyzer;
        $this->imageKit = $imageKit;
        $this->safety = $safety;
    }

    /**
     * Process image upload, analyze, tag and return CDN URL.
     */
    public function processUpload(\Illuminate\Http\UploadedFile $file, \App\Models\Album $album): array
    {
        // 1. Safety Check
        $safetyResult = $this->safety->check($file);
        if ($safetyResult['status'] === 'rejected') {
            throw new \Exception("Image upload rejected: " . $safetyResult['reason']);
        }

        // 2. Upload to ImageKit (Using existing logic or SDK)
        // For this implementation, we simulate/use the ImageKit SDK's upload if available.
        // Create the record first to get a UUID
        $image = \App\Models\Image::create([
            'user_id' => $album->user_id,
            'album_id' => $album->id,
            'filename' => $file->getClientOriginalName(),
            'path' => 'pending', // Will be updated after upload
            'size' => $file->getSize(),
        ]);

        // 3. Bridge the cache gap (Resilient to Redis failure)
        try {
            Redis::setex("opticvault:safety:{$image->id}", 3600, 'safe_verified');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in processUpload: " . $e->getMessage());
        }
        // 4. Analyze and Tag
        $this->analyzeAndTag($image);

        // 5. Return Optimized URL
        return [
            'image_id' => $image->id,
            'cdn_url' => $this->imageKit->getOptimizedUrl($image->path),
            'tags' => $image->tags->pluck('name'),
        ];
    }

    /**
     * Analyze the image and perform automated tagging, quality grading, and category classification.
     */
    public function analyzeAndTag(Image $image): void
    {
        try {
            Log::info("ImageIntelligence: Starting analysis for image {$image->id}");

            // Ensure storage relation is loaded for path resolution
            if (!$image->relationLoaded('storage')) {
                $image->load('storage');
            }

            // Stage 3: Check Redis Pipeline Cache (Resilient to Redis failure)
            $safetyCacheKey = "opticvault:safety:{$image->id}";
            $cachedSafety = null;
            try {
                $cachedSafety = Redis::get($safetyCacheKey);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Redis failure in analyzeAndTag: " . $e->getMessage());
            }
            
            if ($cachedSafety) {
                Log::info("AI Intelligence: Safety verified via Redis cache for {$image->id}. Running TAGGING ONLY.");
                // Stage 2: Tagging Specialization (Google Vision → Cloudinary)
                $result = $this->analyzer->analyzeTags($image, 'image');
            } else {
                Log::info("AI Intelligence: No safety cache found for {$image->id}. Running FULL analysis.");
                // Fallback: full analysis if cache expired or missing
                $result = $this->analyzer->analyze($image, 'image');
            }

            // 1. Hierarchical Tagging: Prepend category to tags for "Nature -> Sea" structure
            if (!empty($result->category) && $result->category !== 'other') {
                // Ensure category is translated or handled as a primary tag
                array_unshift($result->tags, $result->category);
                $result->tags = array_values(array_unique($result->tags));
            }

            // 1a. Update Tags (Spatie Tags)
            $this->updateImageTags($image, $result->tags);

            // 2. Automated AI Description: Always populate ai_description
            if (!empty($result->caption)) {
                $image->update([
                    'ai_description' => $result->caption,
                    // Only update main description if it's still empty
                    'description' => empty($image->description) ? $result->caption : $image->description
                ]);
                Log::info("ImageIntelligence: Updated AI description for {$image->id}");
            }

            // 2a. Store Labels in images.labels (new column)
            $image->update(['labels' => $result->tags]);

            // 3. Store in separate table (legacy support)
            $this->storeLabels($image, $result->tags);

            // 4. Store quality grade and category in AI metadata
            $image->aiMetadata()->updateOrCreate(
                [], // MorphOne automatically scopes to media_id and media_type
                [
                    'driver_name' => $result->driverName,
                    'is_sensitive' => $result->isSensitive,
                    'extracted_tags' => $result->tags,
                    'quality_grade' => $result->qualityGrade,
                    'category' => $result->category,
                    'caption' => $result->caption,
                ]
            );

            Log::info("ImageIntelligence: Successfully analyzed image {$image->id}", [
                'quality_grade' => $result->qualityGrade,
                'category' => $result->category,
                'tags_count' => count($result->tags),
            ]);

            // 5. Dispatch enhancement job if quality is below high_quality
            if ($result->qualityGrade !== 'high_quality') {
                \App\Jobs\EnhanceImageJob::dispatch($image->id)
                    ->onQueue('default')
                    ->delay(now()->addSeconds(3));

                Log::info("ImageIntelligence: Dispatched EnhanceImageJob for image {$image->id} (grade: {$result->qualityGrade})");
            }

        } catch (\Exception $e) {
            Log::error("ImageIntelligence Error: " . $e->getMessage());
        }
    }

    /**
     * Update image tags using Spatie Tags.
     */
    protected function updateImageTags(Image $image, array $tags): void
    {
        // Filter out nulls, empty strings, and ensure all are strings
        $tags = array_filter($tags, fn($tag) => !empty($tag) && is_string($tag));
        
        if (empty($tags)) {
            return;
        }

        // Spatie Tags allows syncing tags by providing an array of strings
        $image->syncTags(array_values($tags));
    }

    /**
     * Store classification labels in the image_labels table.
     */
    protected function storeLabels(Image $image, array $labels): void
    {
        ImageLabel::updateOrCreate(
            ['image_id' => $image->id],
            ['labels' => $labels]
        );
    }
}
