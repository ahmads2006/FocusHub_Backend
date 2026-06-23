<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use Symfony\Component\HttpFoundation\Response;

class CheckSiteOffline
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if the site is offline
        $isOffline = SystemSetting::get('site_offline', false);

        if ($isOffline) {
            // 2. Allow bypassing for admin routes and settings status endpoints
            if ($request->is('api/admin/*') || 
                $request->is('api/v1/admin/*') || 
                $request->is('admin/*') ||
                $request->is('api/v1/system-settings/status') ||
                $request->is('api/system-settings/status')
            ) {
                return $next($request);
            }

            // 3. Bypass for logged-in super-admin/admin users
            // Using both guard check and role checking
            $user = auth('sanctum')->user() ?? $request->user();
            if ($user && (
                $user->hasRole('super-admin') || 
                $user->hasRole('super_admin') || 
                $user->hasRole('admin') || 
                in_array($user->role, ['super-admin', 'super_admin', 'admin'])
            )) {
                return $next($request);
            }

            // 4. Bypass for whitelisted IPs
            $allowedIps = SystemSetting::get('allowed_ips', []);
            if (is_array($allowedIps) && in_array($request->ip(), $allowedIps)) {
                return $next($request);
            }

            // 5. Return 503 Service Unavailable with offline details
            $message = SystemSetting::get('site_offline_message', 'الموقع قيد الصيانة حالياً. سنعود قريباً!');
            $countdown = SystemSetting::get('site_offline_countdown', null);

            return response()->json([
                'status' => 'offline',
                'message' => $message,
                'countdown' => $countdown,
            ], 503);
        }

        return $next($request);
    }
}
