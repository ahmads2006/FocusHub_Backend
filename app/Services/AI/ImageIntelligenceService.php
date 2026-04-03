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

        // 3. Analyze and Tag
        $this->analyzeAndTag($image);

        // 4. Return Optimized URL
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

            // Stage 3: Check Redis Pipeline Cache for existing safety result
            $safetyCacheKey = "opticvault:safety:{$image->id}";
            $cachedSafety = Redis::get($safetyCacheKey);
            
            if ($cachedSafety) {
                Log::info("AI Intelligence: Safety verified via Redis cache for {$image->id}. Running TAGGING ONLY.");
                // Stage 2: Tagging Specialization (Google Vision → Cloudinary)
                $result = $this->analyzer->analyzeTags($image, 'image');
            } else {
                Log::info("AI Intelligence: No safety cache found for {$image->id}. Running FULL analysis.");
                // Fallback: full analysis if cache expired or missing
                $result = $this->analyzer->analyze($image, 'image');
            }

            // 1. Update Tags (Spatie Tags)
            $this->updateImageTags($image, $result->tags);

            // 2. Store Labels in images.labels (new column)
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
        if (empty($tags)) {
            return;
        }

        // Spatie Tags allows syncing tags by providing an array of strings
        $image->syncTags($tags);
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
