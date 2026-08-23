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
            // New Theme Customizer fields
            'maintenance_theme' => SystemSetting::get('maintenance_theme', 'glassmorphic'),
            'maintenance_logo' => SystemSetting::get('maintenance_logo'),
            'maintenance_social_telegram' => SystemSetting::get('maintenance_social_telegram'),
            'maintenance_social_whatsapp' => SystemSetting::get('maintenance_social_whatsapp'),
            'maintenance_social_instagram' => SystemSetting::get('maintenance_social_instagram'),
            // New Broadcast Alert fields
            'broadcast_alert_enabled' => SystemSetting::get('broadcast_alert_enabled', false),
            'broadcast_alert_message' => SystemSetting::get('broadcast_alert_message', ''),
            'broadcast_alert_type' => SystemSetting::get('broadcast_alert_type', 'warning'),
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
            // Customizer & Broadcast
            'maintenance_theme' => $settings->get('maintenance_theme')?->value ?? 'glassmorphic',
            'maintenance_logo' => $settings->get('maintenance_logo')?->value,
            'maintenance_social_telegram' => $settings->get('maintenance_social_telegram')?->value,
            'maintenance_social_whatsapp' => $settings->get('maintenance_social_whatsapp')?->value,
            'maintenance_social_instagram' => $settings->get('maintenance_social_instagram')?->value,
            'broadcast_alert_enabled' => filter_var($settings->get('broadcast_alert_enabled')?->value, FILTER_VALIDATE_BOOLEAN),
            'broadcast_alert_message' => $settings->get('broadcast_alert_message')?->value ?? '',
            'broadcast_alert_type' => $settings->get('broadcast_alert_type')?->value ?? 'warning',
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
            // Customizer & Broadcast validations
            'maintenance_theme' => 'required|string|in:glassmorphic,neon,minimalist',
            'maintenance_logo' => 'nullable|string',
            'maintenance_social_telegram' => 'nullable|string',
            'maintenance_social_whatsapp' => 'nullable|string',
            'maintenance_social_instagram' => 'nullable|string',
            'broadcast_alert_enabled' => 'required|boolean',
            'broadcast_alert_message' => 'nullable|string',
            'broadcast_alert_type' => 'required|string|in:warning,danger,info',
        ]);

        $siteOffline = $request->boolean('site_offline');
        $siteOfflineMessage = $request->input('site_offline_message', 'الموقع قيد الصيانة حالياً. سنعود قريباً!');
        $siteOfflineCountdown = $request->input('site_offline_countdown');
        $allowedIps = $request->input('allowed_ips', []);
        $disabledFeatures = $request->input('disabled_features', []);

        // Customizer & Broadcast variables
        $maintenanceTheme = $request->input('maintenance_theme', 'glassmorphic');
        $maintenanceLogo = $request->input('maintenance_logo');
        $maintenanceSocialTelegram = $request->input('maintenance_social_telegram');
        $maintenanceSocialWhatsapp = $request->input('maintenance_social_whatsapp');
        $maintenanceSocialInstagram = $request->input('maintenance_social_instagram');
        $broadcastAlertEnabled = $request->boolean('broadcast_alert_enabled');
        $broadcastAlertMessage = $request->input('broadcast_alert_message', '');
        $broadcastAlertType = $request->input('broadcast_alert_type', 'warning');

        SystemSetting::set('site_offline', $siteOffline, 'boolean');
        SystemSetting::set('site_offline_message', $siteOfflineMessage, 'string');
        SystemSetting::set('site_offline_countdown', $siteOfflineCountdown, 'string');
        SystemSetting::set('allowed_ips', $allowedIps, 'json');
        SystemSetting::set('disabled_features', $disabledFeatures, 'json');
        
        // Save Customizer & Broadcast
        SystemSetting::set('maintenance_theme', $maintenanceTheme, 'string');
        SystemSetting::set('maintenance_logo', $maintenanceLogo, 'string');
        SystemSetting::set('maintenance_social_telegram', $maintenanceSocialTelegram, 'string');
        SystemSetting::set('maintenance_social_whatsapp', $maintenanceSocialWhatsapp, 'string');
        SystemSetting::set('maintenance_social_instagram', $maintenanceSocialInstagram, 'string');
        SystemSetting::set('broadcast_alert_enabled', $broadcastAlertEnabled, 'boolean');
        SystemSetting::set('broadcast_alert_message', $broadcastAlertMessage, 'string');
        SystemSetting::set('broadcast_alert_type', $broadcastAlertType, 'string');

        // Log action
        $causer = auth()->user() ?? auth('sanctum')->user() ?? $request->user();
        if (class_exists(\Spatie\Activitylog\ActivityLogger::class) || function_exists('activity')) {
            $statusText = $siteOffline ? 'OFFLINE' : 'ONLINE';
            activity()
                ->causedBy($causer)
                ->withProperties([
                    'site_offline' => $siteOffline,
                    'disabled_features' => $disabledFeatures,
                    'allowed_ips' => $allowedIps,
                    'broadcast_alert_enabled' => $broadcastAlertEnabled
                ])
                ->log("System settings updated. Site status: {$statusText}. Alert Broadcast: " . ($broadcastAlertEnabled ? 'ENABLED' : 'DISABLED'));
        }

        return response()->json([
            'message' => __('System settings updated successfully.'),
            'settings' => [
                'site_offline' => $siteOffline,
                'site_offline_message' => $siteOfflineMessage,
                'site_offline_countdown' => $siteOfflineCountdown,
                'allowed_ips' => $allowedIps,
                'disabled_features' => $disabledFeatures,
                'maintenance_theme' => $maintenanceTheme,
                'maintenance_logo' => $maintenanceLogo,
                'maintenance_social_telegram' => $maintenanceSocialTelegram,
                'maintenance_social_whatsapp' => $maintenanceSocialWhatsapp,
                'maintenance_social_instagram' => $maintenanceSocialInstagram,
                'broadcast_alert_enabled' => $broadcastAlertEnabled,
                'broadcast_alert_message' => $broadcastAlertMessage,
                'broadcast_alert_type' => $broadcastAlertType,
            ]
        ]);
    }
}
