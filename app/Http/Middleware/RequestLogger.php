<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        if (str_contains($request->fullUrl(), '/api/v1/share')) {
            Log::info("Global Share Request Log:", [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }
        return $next($request);
    }
}
