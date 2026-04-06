<?php

namespace App\Services\AI\Exceptions;

/**
 * Thrown when an AI driver's API quota/rate limit is exhausted.
 * Carries retry timing info so the Failover Manager can make smarter decisions.
 */
class QuotaExceededException extends AnalyzerException
{
    public function __construct(
        string $message = '',
        string $driverName = 'unknown',
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $driverName, 429, $previous);
    }
}
