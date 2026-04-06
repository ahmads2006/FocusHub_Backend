<?php

namespace App\Services\AI\Exceptions;

/**
 * Thrown when every AI driver in the failover chain has failed.
 * Carries the individual error from each driver for comprehensive logging.
 */
class AllAnalyzersFailedException extends AnalyzerException
{
    /**
     * @param string $message  Summary message
     * @param array<string, \Throwable> $driverErrors  Map of [driverName => exception]
     */
    public function __construct(
        string $message = '',
        public readonly array $driverErrors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 'all', $code, $previous);
    }

    /**
     * Get a formatted summary of all driver failures for logging.
     */
    public function getErrorSummary(): string
    {
        if (empty($this->driverErrors)) {
            return $this->getMessage();
        }

        $lines = [];
        foreach ($this->driverErrors as $driver => $error) {
            $lines[] = "  [{$driver}]: {$error->getMessage()}";
        }

        return $this->getMessage() . "\n" . implode("\n", $lines);
    }
}
