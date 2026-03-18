<?php

namespace App\Services\AI\DTOs;

abstract class AnalysisResult
{
    public function __construct(
        public string $driverName,
        public array $rawResults,
        public float $confidenceScore = 1.0,
    ) {
    }

    /**
     * Convert the final result to an array for storage.
     */
    abstract public function toArray(): array;
}
