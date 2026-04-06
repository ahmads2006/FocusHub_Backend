<?php

namespace App\Services\AI\DTOs;

class VideoAnalysisResult extends AnalysisResult
{
    public function __construct(
        string $driverName,
        array $rawResults,
        public array $labels = [],
        public ?string $transcription = null,
        public array $sceneDetection = [],
        float $confidenceScore = 1.0,
        bool $isSensitive = false,
        string $qualityGrade = 'high_quality',
        ?string $category = null,
    ) {
        parent::__construct(
            driverName: $driverName,
            rawResults: $rawResults,
            confidenceScore: $confidenceScore,
            isSensitive: $isSensitive,
            tags: $labels, // Map video labels to abstract tags
            qualityGrade: $qualityGrade,
            category: $category,
        );
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driverName,
            'tags' => $this->tags,
            'labels' => $this->labels, // Preserved for backward compatibility
            'transcription' => $this->transcription,
            'scene_detection' => $this->sceneDetection,
            'is_sensitive' => $this->isSensitive,
            'confidence_score' => $this->confidenceScore,
            'quality_grade' => $this->qualityGrade,
            'category' => $this->category,
            'caption' => $this->caption,
            'raw_results' => $this->rawResults,
        ];
    }
}
