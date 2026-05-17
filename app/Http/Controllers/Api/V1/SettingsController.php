<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        // Generate a preview URL for the logo if it exists in cloud storage
        $logoPreviewUrl = null;
        $logoPath = $settings?->watermark_logo;
        if ($logoPath && !str_starts_with($logoPath, 'blob:') && !str_starts_with($logoPath, 'data:')) {
            try {
                $logoPreviewUrl = Storage::disk('s3')->temporaryUrl($logoPath, now()->addMinutes(60));
            } catch (\Exception $e) {
                // Fallback: try public disk
                if (Storage::disk('public')->exists($logoPath)) {
                    $logoPreviewUrl = asset('storage/' . $logoPath);
                }
            }
        }

        return response()->json([
            'success' => true,
            'watermark' => [
                'text'     => $settings?->watermark_text ?? '© ' . date('Y') . ' OpalShot',
                'type'     => $settings?->watermark_mode ?? 'text',
                'logoUrl'  => $logoPreviewUrl,
                'logoPath' => $logoPath, // S3 path for backend reference
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
     * Accepts JSON fields + optional watermark_logo file upload.
     */
    public function updateWatermark(Request $request)
    {
        $request->validate([
            'watermark.text'     => 'nullable|string|max:200',
            'watermark.type'     => 'nullable|in:text,logo',
            'watermark.opacity'  => 'nullable|integer|min:0|max:100',
            'watermark.color'    => 'nullable|string|max:20',
            'watermark_logo'     => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        $wm = $request->input('watermark', []);
        $user = Auth::user();

        // Map frontend field names → DB column names
        $updateData = [];
        if (isset($wm['text']))    $updateData['watermark_text']       = $wm['text'];
        if (isset($wm['type']))    $updateData['watermark_mode']       = $wm['type'];
        if (isset($wm['color']))   $updateData['watermark_text_color'] = $wm['color'];
        if (isset($wm['opacity'])) $updateData['watermark_opacity']    = $wm['opacity'] / 100;

        // ─── LOGO FILE UPLOAD: Store to S3 (DigitalOcean Spaces) ───
        if ($request->hasFile('watermark_logo')) {
            $file = $request->file('watermark_logo');
            
            // Delete old logo from storage
            $currentLogo = $user->settings?->watermark_logo;
            if ($currentLogo) {
                try {
                    Storage::disk('s3')->delete($currentLogo);
                } catch (\Exception $e) {
                    // Also try public disk for legacy logos
                    Storage::disk('public')->delete($currentLogo);
                }
            }
            
            // Upload new logo to S3 in a dedicated folder
            $fileName = 'logo_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $s3Path = "watermarks/{$user->id}/{$fileName}";
            
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('s3')->put($s3Path, $stream, [
                'visibility' => 'public',
            ]);
            if (is_resource($stream)) fclose($stream);
            
            $updateData['watermark_logo'] = $s3Path;
            $updateData['watermark_mode'] = 'logo'; // Auto-switch to logo mode
            
            Log::info("Watermark Logo uploaded to S3: {$s3Path} for user {$user->id}");
        }

        // Create or update
        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            $updateData
        );

        // Return the updated settings including preview URL
        $settings = $user->settings()->first();
        $logoPreviewUrl = null;
        if ($settings->watermark_logo && !str_starts_with($settings->watermark_logo, 'blob:')) {
            try {
                $logoPreviewUrl = Storage::disk('s3')->temporaryUrl($settings->watermark_logo, now()->addMinutes(60));
            } catch (\Exception $e) {}
        }

        return response()->json([
            'success' => true,
            'message' => 'Watermark settings updated successfully.',
            'logoUrl' => $logoPreviewUrl,
            'logoPath' => $settings->watermark_logo,
        ]);
    }
}
