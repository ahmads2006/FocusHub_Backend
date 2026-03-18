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
            // we assume $media has a field like 'imagekit_file_id' or we use the URL
            // Based on previous migrations, we might have 'ik_file_id'
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

            return new ImageAnalysisResult(
                driverName: $this->getName(),
                rawResults: (array) $details,
                tags: array_merge($tags, $aiTags),
                isSensitive: false // ImageKit doesn't natively do safety in getFileDetails without addons
            );

        } catch (QuotaExceededException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("ImageKit Analysis Failed: " . $e->getMessage());
            throw new AnalyzerException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
