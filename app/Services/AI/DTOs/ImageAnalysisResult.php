<?php

namespace App\Services\AI\DTOs;

class ImageAnalysisResult extends AnalysisResult
{
    /**
     * @param string $qualityGrade  high_quality | medium_quality | low_quality
     * @param string|null $category  nature | forest | sea | urban | portrait | food | architecture | abstract | other
     */
    public function __construct(
        string $driverName,
        array $rawResults,
        array $tags = [],
        public ?string $ocrText = null,
        bool $isSensitive = false,
        float $confidenceScore = 1.0,
        string $qualityGrade = 'high_quality',
        ?string $category = null,
        ?string $caption = null,
    ) {
        parent::__construct(
            driverName: $driverName,
            rawResults: $rawResults,
            confidenceScore: $confidenceScore,
            isSensitive: $isSensitive,
            tags: $tags,
            qualityGrade: $qualityGrade,
            category: $category,
            caption: $caption,
        );
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driverName,
            'tags' => $this->tags,
            'ocr_text' => $this->ocrText,
            'is_sensitive' => $this->isSensitive,
            'confidence_score' => $this->confidenceScore,
            'quality_grade' => $this->qualityGrade,
            'category' => $this->category,
            'caption' => $this->caption,
            'raw_results' => $this->rawResults,
        ];
    }
}
