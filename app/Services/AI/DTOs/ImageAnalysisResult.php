<?php

namespace App\Services\AI\DTOs;

class ImageAnalysisResult extends AnalysisResult
{
    public function __construct(
        string $driverName,
        array $rawResults,
        public array $tags = [],
        public ?string $ocrText = null,
        public bool $isSensitive = false,
        float $confidenceScore = 1.0,
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
            'raw_results' => $this->rawResults,
        ];
    }
}
