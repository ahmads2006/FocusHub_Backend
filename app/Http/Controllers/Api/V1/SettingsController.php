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
                'text'     => $settings?->watermark_text ?? '© ' . date('Y') . ' OpalShot',
                'type'     => $settings?->watermark_mode ?? 'text',
                'logoUrl'  => $settings?->watermark_logo ?? null,
                'opacity'  => $settings?->watermark_opacity !== null ? (int)($settings->watermark_opacity * 100) : 70,
                'scale'    => 50,
                'glow'     => 5,
                'color'    => $settings?->watermark_text_color ?? '#D4AF37',
                'position' => 'bottom-right',
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
            'watermark.color'    => 'nullable|string|max:20',
        ]);

        $wm = $validated['watermark'] ?? [];
        $user = Auth::user();

        // Map frontend field names → DB column names
        $updateData = [];
        if (isset($wm['text']))    $updateData['watermark_text']       = $wm['text'];
        if (isset($wm['type']))    $updateData['watermark_mode']       = $wm['type'];
        if (isset($wm['logoUrl'])) $updateData['watermark_logo']       = $wm['logoUrl'];
        if (isset($wm['color']))   $updateData['watermark_text_color'] = $wm['color'];
        if (isset($wm['opacity'])) $updateData['watermark_opacity']    = $wm['opacity'] / 100; // Convert 0-100 → 0-1 float

        // Create or update
        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            $updateData
        );

        return response()->json([
            'success' => true,
            'message' => 'Watermark settings updated successfully.',
        ]);
    }
}
