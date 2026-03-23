<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVerified
{
    /**
     * Handle an incoming request.
     * يمنع أي مستخدم من الوصول إلا إذا كان حقل is_verified يساوي 1.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // 1. Permanent DB verification check
        if ($user->is_verified) {
            return $next($request);
        }

        // 2. Session-based 2FA check (for current valid login)
        if (session('2fa_verified')) {
            return $next($request);
        }

        // 3. Trusted Device Cookie check
        $token = $request->cookie('opticvault_trusted_device');
        if ($token) {
            $hashed = hash('sha256', $token);
            $verification = $user->verification;

            if ($verification && 
                $verification->device_token === $hashed && 
                $verification->device_trusted_until && 
                $verification->device_trusted_until->isFuture()) {
                
                // Secondary check: IP hint (non-authoritative but better for tracking)
                if ($verification->last_login_ip !== $request->ip()) {
                    // We could log this or potentially require 2FA if we wanted higher security,
                    // but the user said IP is secondary.
                }

                session(['2fa_verified' => true]);
                return $next($request);
            }
        }

        return redirect()->route('verify.code')
            ->withErrors(['code' => 'يجب عليك التحقق من بريدك الإلكتروني أولاً.']);
    }
}
