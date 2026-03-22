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
        public array $tags = [],
        public ?string $ocrText = null,
        public bool $isSensitive = false,
        float $confidenceScore = 1.0,
        public string $qualityGrade = 'high_quality',
        public ?string $category = null,
    ) {
        parent::__construct($driverName, $rawResults, $confidenceScore);
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
            'raw_results' => $this->rawResults,
        ];
    }
}
