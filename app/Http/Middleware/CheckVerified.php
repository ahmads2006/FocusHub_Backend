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

        // 1. If email is not verified at all, block immediately
        if (!$user->is_verified) {
            return $this->blockRequest($request, __('messages.email_unverified'));
        }

        // 2. Sanctum token-based 2FA check (for stateless API clients using Bearer tokens)
        if ($request->bearerToken()) {
            $token = $user->currentAccessToken();
            if ($token && in_array('2fa-unverified', $token->abilities)) {
                return $this->blockRequest($request, __('messages.2fa_required') ?? 'رمز التحقق مطلوب لتسجيل الدخول من جهاز جديد');
            }
            return $next($request);
        }

        // 3. Session-based 2FA check (for current valid login in web contexts, though we use Sanctum APIs)
        if (session('2fa_verified')) {
            return $next($request);
        }

        // 4. Trusted Device Cookie check (30 days 2FA)
        $token = $request->cookie('opticvault_trusted_device');
        if ($token) {
            $hashed = hash('sha256', $token);
            $verification = $user->verification;

            if ($verification && 
                $verification->device_token === $hashed && 
                $verification->device_trusted_until && 
                $verification->device_trusted_until->isFuture()) {
                
                session(['2fa_verified' => true]);
                return $next($request);
            }
        }

        // Not trusted device or email not verified.
        return $this->blockRequest($request, __('messages.2fa_required') ?? 'رمز التحقق مطلوب لتسجيل الدخول من جهاز جديد');
    }

    private function blockRequest(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('v1/*')) {
            return response()->json([
                'success' => false,
                'requires_verification' => true,
                'needs_2fa' => true,
                'message' => $message,
            ], 403);
        }

        // Fallback for non-API routes (unlikely to hit since we use API routes)
        return redirect(config('app.frontend_url', 'https://www.opalshot.studio') . '/verify');
    }
}
