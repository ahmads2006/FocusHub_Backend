<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class TrackOnlineStatus
{
    /**
     * Track the authenticated user's online status in Redis.
     * Sets a key with 5-minute TTL — if no request comes within 5 min, user is considered offline.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            Redis::setex('user:online:' . Auth::id(), 300, now()->timestamp);
        }

        return $next($request);
    }
}
