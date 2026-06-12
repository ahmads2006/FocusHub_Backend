<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\Security\SteganographyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WatermarkController extends Controller
{
    protected $steganography;

    public function __construct(SteganographyService $steganography)
    {
        $this->steganography = $steganography;
    }

    /**
     * POST /api/v1/watermark/verify
     * Verify the steganographic ownership of a photo.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|max:10240', // max 10MB
        ]);

        $file = $request->file('image');

        // Extract signature and verify
        $payload = $this->steganography->verify($file->getRealPath());

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر التحقق من ملكية الصورة. لم يتم العثور على أي توقيع رقمي مخفي خاص بمنصة OpalShot في هذه الصورة.',
            ], 422);
        }

        // Fetch image details from DB to prove ownership
        $image = Image::withoutGlobalScopes()
            ->with(['user', 'meta', 'moderation'])
            ->find($payload['id']);

        if (!$image) {
            return response()->json([
                'success' => false,
                'message' => 'تم العثور على توقيع رقمي للمنصة، ولكن لم يتم العثور على سجل الصورة المطابق في قاعدة البيانات.',
            ], 404);
        }

        $photographer = $image->user;
        $specs = $image->meta?->technical_specs ?? [];

        return response()->json([
            'success' => true,
            'verified' => true,
            'message' => 'تم التحقق من ملكية الصورة بنجاح! هذه الصورة مملوكة قانونياً للمصور المسجل لدينا.',
            'details' => [
                'image_id' => $image->id,
                'title' => $image->title,
                'filename' => $image->filename,
                'upload_date' => $image->created_at ? $image->created_at->toIso8601String() : null,
                'photographer' => [
                    'id' => $photographer->id,
                    'name' => $photographer->name,
                    'email' => $photographer->email,
                    'avatar_url' => $photographer->avatar ? asset('storage/' . $photographer->avatar) : null,
                ],
                'camera_specs' => [
                    'model' => $specs['camera_model'] ?? 'N/A',
                    'aperture' => $specs['aperture'] ?? 'N/A',
                    'shutter_speed' => $specs['shutter_speed'] ?? 'N/A',
                    'iso' => $specs['iso'] ?? 'N/A',
                    'focal_length' => $specs['focal_length'] ?? 'N/A',
                ]
            ]
        ]);
    }
}
