<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ConnectionController extends Controller
{
    /**
     * Toggle follow/unfollow a user.
     */
    public function toggle(User $user): JsonResponse
    {
        $currentUserId = Auth::id();

        if ($currentUserId === $user->id) {
            return response()->json(['success' => false, 'message' => 'لا يمكنك متابعة نفسك.'], 400);
        }

        $connection = Connection::where(function ($q) use ($currentUserId, $user) {
            $q->where('user_id', $currentUserId)->where('connected_user_id', $user->id);
        })->orWhere(function ($q) use ($currentUserId, $user) {
            $q->where('user_id', $user->id)->where('connected_user_id', $currentUserId);
        })->first();

        if ($connection) {
            $connection->delete();
            return response()->json([
                'success' => true,
                'status'  => 'unfollowed',
                'message' => 'تم إلغاء المتابعة.',
            ]);
        }

        Connection::create([
            'user_id'           => $currentUserId,
            'connected_user_id' => $user->id,
            'status'            => 'accepted',
        ]);

        return response()->json([
            'success' => true,
            'status'  => 'followed',
            'message' => 'تم المتابعة بنجاح.',
        ]);
    }

    /**
     * Get connection status with a specific user.
     */
    public function status(User $user): JsonResponse
    {
        $currentUserId = Auth::id();

        $connection = Connection::where(function ($q) use ($currentUserId, $user) {
            $q->where('user_id', $currentUserId)->where('connected_user_id', $user->id);
        })->orWhere(function ($q) use ($currentUserId, $user) {
            $q->where('user_id', $user->id)->where('connected_user_id', $currentUserId);
        })->first();

        return response()->json([
            'success'      => true,
            'connected'    => (bool) $connection,
            'status'       => $connection->status ?? null,
            'is_requester' => $connection ? ($connection->user_id === $currentUserId) : false,
        ]);
    }

    /**
     * List all accepted connections for the current user.
     */
    public function index(): JsonResponse
    {
        $connections = Auth::user()->acceptedConnections()
            ->with(['profile'])
            ->get()
            ->map(fn($conn) => [
                'id'                => $conn->id,
                'name'              => $conn->name,
                'avatar'            => $conn->avatar,
                'is_badge_verified' => (bool) $conn->is_badge_verified,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $connections,
        ]);
    }
}
