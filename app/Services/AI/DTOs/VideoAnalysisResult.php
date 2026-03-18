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
    ) {
        parent::__construct($driverName, $rawResults, $confidenceScore);
    }

    public function toArray(): array
    {
        return [
            'driver' => $this->driverName,
            'labels' => $this->labels,
            'transcription' => $this->transcription,
            'scene_detection' => $this->sceneDetection,
            'confidence_score' => $this->confidenceScore,
            'raw_results' => $this->rawResults,
        ];
    }
}
