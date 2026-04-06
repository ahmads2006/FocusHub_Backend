<?php

namespace App\Services\AI\Exceptions;

use Exception;

/**
 * Base exception for all AI Analyzer errors.
 * Provides a shared context layer (driver name) for debugging and logging.
 */
class AnalyzerException extends Exception
{
    public function __construct(
        string $message = '',
        public readonly string $driverName = 'unknown',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
