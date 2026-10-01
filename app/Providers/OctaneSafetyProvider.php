<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Facades\Octane;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * OctaneSafetyProvider
 *
 * This provider registers listeners for Octane lifecycle events
 * to prevent memory leaks, stale state, and ensure clean request isolation.
 *
 * Safety mechanisms:
 * 1. Flushes DB query log after each request (prevents unbounded array growth)
 * 2. Resets any static caches that could leak across requests
 * 3. Monitors peak memory and logs warnings when approaching limits
 * 4. Clears Firestore token cache periodically to prevent stale tokens
 */
class OctaneSafetyProvider extends ServiceProvider
{
    /**
     * Memory threshold in bytes (128 MB).
     * If a worker exceeds this after a request, it will be flagged for restart.
     */
    private const MEMORY_WARNING_THRESHOLD = 128 * 1024 * 1024;

    /**
     * Memory hard limit in bytes (192 MB).
     * Workers approaching this will be terminated gracefully.
     */
    private const MEMORY_HARD_LIMIT = 192 * 1024 * 1024;

    /**
     * Counter for requests processed by this worker instance.
     */
    private static int $requestCount = 0;

    /**
     * Interval (in requests) for periodic maintenance tasks.
     */
    private const MAINTENANCE_INTERVAL = 100;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Only register listeners when running under Octane
        if (!$this->isRunningUnderOctane()) {
            return;
        }

        $this->registerRequestReceivedListener();
        $this->registerRequestTerminatedListener();
        $this->registerWorkerStartingListener();
    }

    /**
     * Before each request: ensure clean state.
     */
    private function registerRequestReceivedListener(): void
    {
        Event::listen(RequestReceived::class, function (RequestReceived $event) {
            // Increment request counter for this worker
            self::$requestCount++;

            // Disable query logging in production to prevent memory buildup
            if (app()->environment('production')) {
                \Illuminate\Support\Facades\DB::disableQueryLog();
            }
        });
    }

    /**
     * After each request: cleanup and monitor.
     */
    private function registerRequestTerminatedListener(): void
    {
        Event::listen(RequestTerminated::class, function (RequestTerminated $event) {
            // 1. Flush query log to prevent memory accumulation
            $this->flushQueryLogs();

            // 2. Clear any temporary data stored in request lifecycle
            $this->clearRequestScopedState();

            // 3. Monitor memory usage
            $this->checkMemoryUsage();

            // 4. Periodic maintenance (every N requests)
            if (self::$requestCount % self::MAINTENANCE_INTERVAL === 0) {
                $this->periodicMaintenance();
            }
        });
    }

    /**
     * When a worker starts: log and initialize.
     */
    private function registerWorkerStartingListener(): void
    {
        Event::listen(WorkerStarting::class, function () {
            self::$requestCount = 0;

            Log::channel('single')->info('[Octane] Worker started', [
                'pid'            => getmypid(),
                'memory_initial' => $this->formatBytes(memory_get_usage(true)),
            ]);
        });
    }

    /**
     * Flush all database query logs to prevent unbounded array growth.
     */
    private function flushQueryLogs(): void
    {
        foreach (\Illuminate\Support\Facades\DB::getConnections() as $connection) {
            $connection->flushQueryLog();
        }
    }

    /**
     * Clear state that should not persist between requests.
     */
    private function clearRequestScopedState(): void
    {
        // Clear resolved Guzzle HTTP client instances if stored in singletons
        // This prevents connection pooling issues with stale connections
        try {
            if (app()->resolved(\GuzzleHttp\Client::class)) {
                app()->forgetInstance(\GuzzleHttp\Client::class);
            }
        } catch (\Throwable $e) {
            // Silently ignore — not critical
        }
    }

    /**
     * Check memory usage and log warnings.
     */
    private function checkMemoryUsage(): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        if ($currentMemory >= self::MEMORY_HARD_LIMIT) {
            Log::channel('single')->error('[Octane] ⚠️ Worker memory CRITICAL — requesting restart', [
                'pid'          => getmypid(),
                'current'      => $this->formatBytes($currentMemory),
                'peak'         => $this->formatBytes($peakMemory),
                'request_num'  => self::$requestCount,
            ]);

            // Signal the worker to stop after this request (Octane will spawn a new one)
            if (function_exists('posix_kill')) {
                posix_kill(getmypid(), SIGTERM);
            }
        } elseif ($currentMemory >= self::MEMORY_WARNING_THRESHOLD) {
            Log::channel('single')->warning('[Octane] Worker memory elevated', [
                'pid'          => getmypid(),
                'current'      => $this->formatBytes($currentMemory),
                'peak'         => $this->formatBytes($peakMemory),
                'request_num'  => self::$requestCount,
            ]);
        }
    }

    /**
     * Periodic maintenance — runs every MAINTENANCE_INTERVAL requests.
     */
    private function periodicMaintenance(): void
    {
        // 1. Force garbage collection
        gc_collect_cycles();

        // 2. Reset Firestore static token cache to force refresh
        // (tokens last 3300s = 55min, this is a safety net)
        $this->resetFirestoreTokenCache();

        // 3. Log health status
        Log::channel('single')->info('[Octane] Worker maintenance checkpoint', [
            'pid'           => getmypid(),
            'requests'      => self::$requestCount,
            'memory_current' => $this->formatBytes(memory_get_usage(true)),
            'memory_peak'   => $this->formatBytes(memory_get_peak_usage(true)),
        ]);
    }

    /**
     * Reset Firestore static token cache.
     * The token will be re-fetched on the next Firestore API call.
     */
    private function resetFirestoreTokenCache(): void
    {
        try {
            $reflection = new \ReflectionClass(\App\Services\Firebase\FirestoreService::class);

            $tokenProp = $reflection->getProperty('cachedToken');
            $tokenProp->setAccessible(true);
            $tokenProp->setValue(null, null);

            $expiryProp = $reflection->getProperty('tokenExpiry');
            $expiryProp->setAccessible(true);
            $expiryProp->setValue(null, null);
        } catch (\Throwable $e) {
            // Class may not exist or properties changed — not critical
        }
    }

    /**
     * Check if the app is running under Octane.
     */
    private function isRunningUnderOctane(): bool
    {
        return isset($_SERVER['LARAVEL_OCTANE']) || class_exists(\Laravel\Octane\Octane::class);
    }

    /**
     * Format bytes into human-readable string.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
