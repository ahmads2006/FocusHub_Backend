<?php

namespace App\Services\AI\DTOs;

abstract class AnalysisResult
{
    public function __construct(
        public string $driverName,
        public array $rawResults,
        public float $confidenceScore = 1.0,
        public bool $isSensitive = false,
        public array $tags = [],
        public string $qualityGrade = 'high_quality',
        public ?string $category = null,
        public ?string $caption = null,
    ) {
    }

    /**
     * Convert the final result to an array for storage.
     */
    abstract public function toArray(): array;
}
