<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\ImageAppeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppealController extends Controller
{
    /**
     * List the current user's appeal history.
     */
    public function index(): JsonResponse
    {
        $appeals = ImageAppeal::with(['image.storage'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $appeals,
        ]);
    }

    /**
     * Submit an appeal for a rejected image.
     */
    public function store(Request $request, Image $image): JsonResponse
    {
        if ($image->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.unauthorized_appeal'),
            ], 403);
        }

        if ($image->status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => __('messages.image_not_banned'),
            ], 400);
        }

        // Check for pending appeal
        if ($image->appeals()->where('status', 'pending')->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.appeal_pending'),
            ], 409);
        }

        // One-appeal limit
        if ($image->appeals()->where('status', 'rejected')->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.appeal_already_rejected'),
            ], 403);
        }

        $validated = $request->validate([
            'contact_name'  => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'reason'        => 'required|string|min:10|max:1000',
        ]);

        $appeal = new ImageAppeal([
            'user_id'       => Auth::id(),
            'contact_name'  => $validated['contact_name'],
            'contact_email' => $validated['contact_email'],
            'reason'        => $validated['reason'],
            'status'        => 'pending',
        ]);

        $image->appeals()->save($appeal);

        return response()->json([
            'success' => true,
            'message' => __('messages.appeal_sent'),
            'data'    => $appeal,
        ], 201);
    }

    // ══════════════════════════════════════
    // ADMIN ENDPOINTS
    // ══════════════════════════════════════

    /**
     * List pending appeals (admin).
     */
    public function adminIndex(): JsonResponse
    {
        $appeals = ImageAppeal::with(['image.storage', 'image.user', 'user'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $appeals,
        ]);
    }

    /**
     * Approve an appeal (admin).
     */
    public function approve(ImageAppeal $appeal): JsonResponse
    {
        $appeal->update(['status' => 'approved', 'reviewed_at' => now()]);

        // Restore image to approved status
        $image = $appeal->image;
        if ($image) {
            $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                'status'     => Image::STATUS_APPROVED,
                'is_visible' => true,
            ]);
            $image->withoutGlobalScopes()->touch();
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.appeal_accepted'),
        ]);
    }

    /**
     * Reject an appeal (admin).
     */
    public function reject(ImageAppeal $appeal): JsonResponse
    {
        $appeal->update(['status' => 'rejected', 'reviewed_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => __('messages.appeal_rejected'),
        ]);
    }
}
