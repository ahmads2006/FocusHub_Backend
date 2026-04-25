<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * Report an image for policy violations.
     */
    public function store(Request $request, Image $image): JsonResponse
    {
        $request->validate([
            'reason'  => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate reporting
        $exists = ImageReport::where('user_id', Auth::id())
            ->where('image_id', $image->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => __('messages.already_reported'),
            ], 409);
        }

        ImageReport::create([
            'user_id'  => Auth::id(),
            'image_id' => $image->id,
            'reason'   => $request->reason,
            'details'  => $request->details,
        ]);

        // Auto-Hide Logic: If reports >= 3, move to under_review
        $reportCount = $image->reports()->where('status', 'open')->count();
        if ($reportCount >= 3 && $image->status === Image::STATUS_APPROVED) {
            $image->update(['status' => Image::STATUS_UNDER_REVIEW]);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.report_received'),
        ], 201);
    }
}
