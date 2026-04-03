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
     * Specialized analysis method for extracting tags and metadata only.
     * Often used when safety analysis is already cached or performed separately.
     * 
     * @param Model $media
     * @param string $mediaType
     * @return AnalysisResult
     */
    public function analyzeTags(Model $media, string $mediaType = 'image'): AnalysisResult;

    /**
     * Analyze a raw uploaded file (pre-upload validation).
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $mediaType image or video
     * @return AnalysisResult
     * @throws \App\Services\AI\Exceptions\AnalyzerException
     */
    public function analyzeFile(\Illuminate\Http\UploadedFile $file, string $mediaType = 'image'): AnalysisResult;

    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string;
}
