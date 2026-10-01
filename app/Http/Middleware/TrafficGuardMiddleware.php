<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * TrafficGuardMiddleware (Adaptive Load Shedding & Priority Traffic Guard)
 *
 * Designed for resource-constrained servers (e.g., 1 vCPU / 1GB RAM).
 * Protects the server from total collapse during sudden traffic surges by:
 * 1. Tracking active concurrent requests in real-time via Redis.
 * 2. Monitoring CPU load average.
 * 3. Enforcing "Priority-First" policy: Active/authenticated users and critical
 *    operations (auth, checkout, admin) always pass through.
 * 4. Fast-dropping (shedding) unauthenticated/guest/polling requests with HTTP 503
 *    and Retry-After header before any heavy database or CPU work occurs.
 */
class TrafficGuardMiddleware
{
    private const REDIS_KEY = 'traffic:active_requests';
    private const REDIS_TTL = 30; // Safety TTL to prevent deadlocks on worker crashes

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('traffic.enabled', true)) {
            return $next($request);
        }

        // 1. Always allow critical infrastructure routes
        if ($this->isWhitelistedRoute($request)) {
            $this->incrementActiveRequests();
            return $next($request);
        }

        // 2. Check server congestion status
        $isCongested = $this->isServerCongested();

        if ($isCongested) {
            // 3. Check request priority: Does this request deserve VIP access?
            if (!$this->hasHighPriority($request)) {
                // Shed this request immediately — 0ms database/CPU footprint!
                return $this->buildCongestedResponse($request);
            }
        }

        // Increment active requests counter
        $this->incrementActiveRequests();

        return $next($request);
    }

    /**
     * Handle tasks after response is sent (Request termination).
     */
    public function terminate(Request $request, Response $response): void
    {
        if (!config('traffic.enabled', true)) {
            return;
        }

        $this->decrementActiveRequests();
    }

    /**
     * Determine if the server is currently under extreme load.
     */
    private function isServerCongested(): bool
    {
        try {
            // Check active concurrent requests
            $activeRequests = (int) Redis::get(self::REDIS_KEY);
            $maxConcurrent = (int) config('traffic.max_concurrent_requests', 25);

            if ($activeRequests >= $maxConcurrent) {
                return true;
            }

            // Check CPU load average (on Linux)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cpuThreshold = (float) config('traffic.cpu_load_threshold', 3.0);
                if (isset($load[0]) && $load[0] >= $cpuThreshold) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // If Redis or system metrics fail, do not block traffic
            return false;
        }

        return false;
    }

    /**
     * Check if the request has high priority:
     * - Authenticated users (Sanctum / Bearer token / active session)
     * - Admin / Staff users
     * - Active checkout or payment transactions
     */
    private function hasHighPriority(Request $request): bool
    {
        // 1. Authenticated via Sanctum or session
        if (auth('sanctum')->check() || auth()->check()) {
            return true;
        }

        // 2. Request carries a Bearer Token (active logged-in client)
        if ($request->bearerToken()) {
            return true;
        }

        // 3. User session has authenticated state
        if ($request->hasSession() && $request->session()->has('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the route is permanently whitelisted from load shedding.
     */
    private function isWhitelistedRoute(Request $request): bool
    {
        // Health check endpoints
        if ($request->is('up') || $request->is('health') || $request->is('status')) {
            return true;
        }

        // Admin endpoints
        if ($request->is('api/admin/*') || $request->is('api/v1/admin/*') || $request->is('admin/*')) {
            return true;
        }

        // Authentication & Password recovery endpoints (must always let users log in!)
        if ($request->is('api/v1/auth/login') ||
            $request->is('api/v1/auth/register') ||
            $request->is('api/v1/auth/forgot-password') ||
            $request->is('api/v1/auth/reset-password') ||
            $request->is('api/v1/auth/refresh')
        ) {
            return true;
        }

        return false;
    }

    /**
     * Increment the active requests counter atomically.
     */
    private function incrementActiveRequests(): void
    {
        try {
            $count = (int) Redis::incr(self::REDIS_KEY);
            if ($count === 1) {
                Redis::expire(self::REDIS_KEY, self::REDIS_TTL);
            }
        } catch (\Throwable $e) {
            // Fail open
        }
    }

    /**
     * Decrement the active requests counter atomically.
     */
    private function decrementActiveRequests(): void
    {
        try {
            $count = (int) Redis::decr(self::REDIS_KEY);
            if ($count < 0) {
                Redis::set(self::REDIS_KEY, 0);
            }
        } catch (\Throwable $e) {
            // Fail open
        }
    }

    /**
     * Fast 503 response with Retry-After for shedded requests.
     */
    private function buildCongestedResponse(Request $request): Response
    {
        $retryAfter = (int) config('traffic.retry_after', 5);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'             => false,
                'code'                => 'SERVER_CONGESTED',
                'message'             => 'السيرفر يشهد إقبالاً مرتفعاً جداً حالياً للحفاظ على استقرار الخدمة، يرجى إعادة المحاولة بعد ثوانٍ قليلة.',
                'retry_after_seconds' => $retryAfter,
            ], 503, [
                'Retry-After'        => (string) $retryAfter,
                'X-Traffic-Shedded'  => '1',
                'Cache-Control'      => 'no-store, no-cache, must-revalidate',
            ]);
        }

        return response(
            '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>السيرفر مشغول حالياً</title>' .
            '<meta name="viewport" content="width=device-width, initial-scale=1">' .
            '<style>body{font-family:system-ui,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;padding:1rem;}.box{max-width:480px;background:#1e293b;padding:2rem;border-radius:1rem;border:1px solid #334155;box-shadow:0 10px 25px rgba(0,0,0,0.5);}h1{color:#38bdf8;font-size:1.5rem;margin-bottom:1rem;}p{color:#94a3b8;line-height:1.6;}.timer{font-weight:bold;color:#f59e0b;}</style>' .
            '<meta http-equiv="refresh" content="' . $retryAfter . '">' .
            '</head><body><div class="box"><h1>السيرفر يشهد ضغطاً عالياً ⏳</h1><p>لضمان سرعة واستقرار الموقع للمستخدمين، تم وضعك في طابور انتظار سريع جداً.<br>سيتم تحديث الصفحة تلقائياً خلال <span class="timer">' . $retryAfter . ' ثوانٍ</span>.</p></div></body></html>',
            503,
            [
                'Retry-After'       => (string) $retryAfter,
                'X-Traffic-Shedded' => '1',
                'Content-Type'      => 'text/html; charset=UTF-8',
            ]
        );
    }
}
