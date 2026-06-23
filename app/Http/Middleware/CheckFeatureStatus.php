<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSetting;
use Symfony\Component\HttpFoundation\Response;

class CheckFeatureStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Get disabled features list
        $disabledFeatures = SystemSetting::get('disabled_features', []);
        if (empty($disabledFeatures) || !is_array($disabledFeatures)) {
            return $next($request);
        }

        // 2. Map request paths to feature names
        $feature = null;
        if ($request->is('api/v1/albums*') || $request->is('api/albums*')) {
            $feature = 'albums';
        } elseif ($request->is('api/v1/chat*') || $request->is('api/chat*')) {
            $feature = 'chat';
        } elseif ($request->is('api/v1/tasks*') || $request->is('api/tasks*')) {
            $feature = 'tasks';
        } elseif ($request->is('api/v1/support*') && !$request->is('api/v1/support/admin*')) {
            $feature = 'support';
        }

        // 3. Block if feature matches and is in disabled list
        if ($feature && in_array($feature, $disabledFeatures)) {
            // Bypass for super-admin/admin
            $user = auth('sanctum')->user() ?? $request->user();
            if ($user && (
                $user->hasRole('super-admin') || 
                $user->hasRole('super_admin') || 
                $user->hasRole('admin') || 
                in_array($user->role, ['super-admin', 'super_admin', 'admin'])
            )) {
                return $next($request);
            }

            // Bypass for whitelisted IPs
            $allowedIps = SystemSetting::get('allowed_ips', []);
            if (is_array($allowedIps) && in_array($request->ip(), $allowedIps)) {
                return $next($request);
            }

            return response()->json([
                'status' => 'feature_disabled',
                'feature' => $feature,
                'message' => __('This feature is temporarily disabled for maintenance.')
            ], 403);
        }

        return $next($request);
    }
}
