<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('super_admin')) {
            return $next($request);
        }

        if ($user && ($user->is_banned || ($user->userStatus?->is_deleted ?? false))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Your account has been banned. Please contact support.'),
                ], 403);
            }

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Your account has been banned. Please contact support.'),
            ]);
        }

        return $next($request);
    }
}
