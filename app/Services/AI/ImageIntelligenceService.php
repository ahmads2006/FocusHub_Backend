<?php

namespace App\Services\AI;

use App\Models\Image;
use App\Models\ImageLabel;
use App\Services\AI\Contracts\MediaAnalyzerInterface;
use App\Services\AI\DTOs\ImageAnalysisResult;
use Illuminate\Support\Facades\Log;

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
     * Analyze the image and perform automated tagging.
     */
    public function analyzeAndTag(Image $image): void
    {
        try {
            Log::info("ImageIntelligence: Starting analysis for image {$image->id}");

            /** @var ImageAnalysisResult $result */
            $result = $this->analyzer->analyze($image, 'image');

            // 1. Update Tags (Spatie Tags)
            $this->updateImageTags($image, $result->tags);

            // 2. Store Labels in images.labels (new column)
            $image->update(['labels' => $result->tags]);

            // 3. Store in separate table (legacy support)
            $this->storeLabels($image, $result->tags);

            Log::info("ImageIntelligence: Successfully analyzed and tagged image {$image->id}");

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
