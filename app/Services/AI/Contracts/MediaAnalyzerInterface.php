<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AnalysisResult;
use Illuminate\Database\Eloquent\Model;

interface MediaAnalyzerInterface
{
    /**
     * Analyze a media model (Image or Video).
     *
     * @param Model $media
     * @param string $mediaType image or video
     * @return AnalysisResult
     * @throws \App\Services\AI\Exceptions\AnalyzerException
     */
    public function analyze(Model $media, string $mediaType = 'image'): AnalysisResult;

    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string;
}
