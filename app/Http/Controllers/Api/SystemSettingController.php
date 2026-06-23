<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    /**
     * Get site status (public)
     */
    public function getStatus(Request $request): JsonResponse
    {
        $siteOffline = SystemSetting::get('site_offline', false);
        $disabledFeatures = SystemSetting::get('disabled_features', []);

        // Check if current user is admin/super-admin
        $user = auth('sanctum')->user() ?? $request->user();
        $isAdmin = false;
        if ($user) {
            $isAdmin = $user->hasRole('super-admin') || 
                       $user->hasRole('super_admin') || 
                       $user->hasRole('admin') || 
                       in_array($user->role, ['super-admin', 'super_admin', 'admin']);
        }

        // Check if IP is whitelisted
        $allowedIps = SystemSetting::get('allowed_ips', []);
        $isIpWhitelisted = is_array($allowedIps) && in_array($request->ip(), $allowedIps);

        $bypassOffline = $isAdmin || $isIpWhitelisted;

        return response()->json([
            'site_offline' => $siteOffline,
            'site_offline_message' => SystemSetting::get('site_offline_message', 'الموقع قيد الصيانة حالياً. سنعود قريباً!'),
            'site_offline_countdown' => SystemSetting::get('site_offline_countdown'),
            'disabled_features' => $disabledFeatures,
            'bypass_offline' => $bypassOffline,
            'user_ip' => $request->ip(),
        ]);
    }

    /**
     * Get all settings (Admin only)
     */
    public function index(): JsonResponse
    {
        $settings = SystemSetting::all()->keyBy('key');
        
        return response()->json([
            'site_offline' => filter_var($settings->get('site_offline')?->value, FILTER_VALIDATE_BOOLEAN),
            'site_offline_message' => $settings->get('site_offline_message')?->value ?? '',
            'site_offline_countdown' => $settings->get('site_offline_countdown')?->value,
            'allowed_ips' => json_decode($settings->get('allowed_ips')?->value ?? '[]', true),
            'disabled_features' => json_decode($settings->get('disabled_features')?->value ?? '[]', true),
        ]);
    }

    /**
     * Update settings (Admin only)
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'site_offline' => 'required|boolean',
            'site_offline_message' => 'nullable|string',
            'site_offline_countdown' => 'nullable|string',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
            'disabled_features' => 'nullable|array',
            'disabled_features.*' => 'string|in:albums,chat,tasks,support',
        ]);

        $siteOffline = $request->boolean('site_offline');
        $siteOfflineMessage = $request->input('site_offline_message', 'الموقع قيد الصيانة حالياً. سنعود قريباً!');
        $siteOfflineCountdown = $request->input('site_offline_countdown');
        $allowedIps = $request->input('allowed_ips', []);
        $disabledFeatures = $request->input('disabled_features', []);

        SystemSetting::set('site_offline', $siteOffline, 'boolean');
        SystemSetting::set('site_offline_message', $siteOfflineMessage, 'string');
        SystemSetting::set('site_offline_countdown', $siteOfflineCountdown, 'string');
        SystemSetting::set('allowed_ips', $allowedIps, 'json');
        SystemSetting::set('disabled_features', $disabledFeatures, 'json');

        // Log action
        $causer = auth()->user() ?? auth('sanctum')->user() ?? $request->user();
        if (class_exists(\Spatie\Activitylog\ActivityLogger::class) || function_exists('activity')) {
            $statusText = $siteOffline ? 'OFFLINE' : 'ONLINE';
            activity()
                ->causedBy($causer)
                ->withProperties([
                    'site_offline' => $siteOffline,
                    'disabled_features' => $disabledFeatures,
                    'allowed_ips' => $allowedIps
                ])
                ->log("System settings updated. Site status: {$statusText}. Disabled features: " . implode(', ', $disabledFeatures));
        }

        return response()->json([
            'message' => __('System settings updated successfully.'),
            'settings' => [
                'site_offline' => $siteOffline,
                'site_offline_message' => $siteOfflineMessage,
                'site_offline_countdown' => $siteOfflineCountdown,
                'allowed_ips' => $allowedIps,
                'disabled_features' => $disabledFeatures,
            ]
        ]);
    }
}
