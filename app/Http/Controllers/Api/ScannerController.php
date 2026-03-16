<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScannerController extends Controller
{
    /**
     * Receive a violation report from the Python background scanner.
     * Protected by SCANNER_API_KEY.
     */
    public function report(Request $request)
    {
        // Validate the secret key
        $expectedKey = config('services.scanner.api_key');
        if (!$expectedKey || $request->header('X-Scanner-Key') !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'image_id'   => 'required|string|exists:images,id',
            'reason'     => 'required|string|max:255',
            'details'    => 'nullable|string|max:1000',
        ]);

        $image = Image::withoutGlobalScopes()->find($validated['image_id']);

        if (!$image) {
            return response()->json(['error' => 'Image not found'], 404);
        }

        // Prevent duplicate AI scanner reports
        $alreadyReported = ImageReport::where('image_id', $image->id)
            ->where('user_id', null)  // AI scanner reports have no user_id
            ->where('reason', $validated['reason'])
            ->where('status', 'open')
            ->exists();

        if ($alreadyReported) {
            return response()->json(['message' => 'Already reported, skipping duplicate.'], 200);
        }

        ImageReport::create([
            'user_id'  => null,  // AI scanner, not a human user
            'image_id' => $image->id,
            'reason'   => $validated['reason'],
            'details'  => $validated['details'] ?? null,
            'status'   => 'open',
        ]);

        Log::info("[Scanner] Reported image {$image->id} for: {$validated['reason']}");

        // Auto-move to under_review if 3+ reports
        $reportCount = ImageReport::where('image_id', $image->id)
            ->where('status', 'open')
            ->count();

        if ($reportCount >= 3 && $image->status === 'approved') {
            $image->moderation()->update(['status' => 'under_review', 'is_visible' => false]);
            Log::info("[Scanner] Image {$image->id} moved to under_review after {$reportCount} reports.");
        }

        return response()->json(['message' => 'Report submitted successfully.'], 201);
    }

    /**
     * Return a list of public gallery images for the scanner to inspect.
     * Protected by SCANNER_API_KEY.
     */
    public function publicImages(Request $request)
    {
        $expectedKey = config('services.scanner.api_key');
        if (!$expectedKey || $request->header('X-Scanner-Key') !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $images = Image::withoutGlobalScopes()
            ->whereHas('moderation', fn($q) => $q->where('status', 'approved'))
            ->where('privacy', 'public')
            ->with('storage')
            ->select('id', 'filename')
            ->limit(500)
            ->get()
            ->map(fn($img) => [
                'id'       => $img->id,
                'filename' => $img->filename,
                's3_key'   => $img->storage?->path,
            ]);

        return response()->json($images);
    }
}
