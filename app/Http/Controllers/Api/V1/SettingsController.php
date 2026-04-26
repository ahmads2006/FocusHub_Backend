<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * GET /api/v1/settings/watermark
     * Get the user's watermark settings.
     */
    public function getWatermark()
    {
        $user = Auth::user();
        $settings = $user->settings;

        return response()->json([
            'success' => true,
            'watermark' => [
                'text'     => $settings->watermark_text ?? '© ' . date('Y') . ' OpalShot',
                'type'     => $settings->watermark_mode ?? 'text',
                'logoUrl'  => $settings->watermark_logo_url ?? null,
                'opacity'  => $settings->watermark_opacity ?? 70,
                'scale'    => $settings->watermark_scale ?? 50,
                'glow'     => $settings->watermark_glow ?? 5,
                'color'    => $settings->watermark_color ?? '#D4AF37',
                'position' => $settings->watermark_position ?? 'bottom-right',
            ],
        ]);
    }

    /**
     * POST /api/v1/settings/watermark
     * Update the user's watermark settings.
     */
    public function updateWatermark(Request $request)
    {
        $validated = $request->validate([
            'watermark.text'     => 'nullable|string|max:200',
            'watermark.type'     => 'nullable|in:text,logo',
            'watermark.logoUrl'  => 'nullable|string|max:500',
            'watermark.opacity'  => 'nullable|integer|min:0|max:100',
            'watermark.scale'    => 'nullable|integer|min:10|max:100',
            'watermark.glow'     => 'nullable|integer|min:0|max:20',
            'watermark.color'    => 'nullable|string|max:20',
            'watermark.position' => 'nullable|in:top-left,top-right,bottom-left,bottom-right,center',
        ]);

        $wm = $validated['watermark'] ?? [];
        $user = Auth::user();
        $settings = $user->settings;

        if ($settings) {
            $settings->update([
                'watermark_text'     => $wm['text'] ?? $settings->watermark_text,
                'watermark_mode'     => $wm['type'] ?? $settings->watermark_mode,
                'watermark_logo_url' => $wm['logoUrl'] ?? $settings->watermark_logo_url,
                'watermark_opacity'  => $wm['opacity'] ?? $settings->watermark_opacity,
                'watermark_scale'    => $wm['scale'] ?? $settings->watermark_scale,
                'watermark_glow'     => $wm['glow'] ?? $settings->watermark_glow,
                'watermark_color'    => $wm['color'] ?? $settings->watermark_color,
                'watermark_position' => $wm['position'] ?? $settings->watermark_position,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Watermark settings updated successfully.',
        ]);
    }
}
