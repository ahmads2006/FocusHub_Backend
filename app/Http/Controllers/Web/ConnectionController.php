<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConnectionController extends Controller
{
    /**
     * Send or accept a connection request (Follow).
     */
    public function toggle(User $user)
    {
        $currentUserId = Auth::id();
        
        if ($currentUserId === $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot follow yourself.'], 400);
        }

        // Check if a connection already exists
        $connection = Connection::where(function($q) use ($currentUserId, $user) {
            $q->where('user_id', $currentUserId)->where('connected_user_id', $user->id);
        })->orWhere(function($q) use ($currentUserId, $user) {
            $q->where('user_id', $user->id)->where('connected_user_id', $currentUserId);
        })->first();

        if ($connection) {
            // If exists, toggle/remove (unfollow) or just return status
            // For now, let's make it an "Unfollow" if it's already accepted or pending
            $connection->delete();
            return response()->json(['success' => true, 'status' => 'unfollowed', 'message' => 'Unfollowed successfully.']);
        }

        // Create new accepted connection (Instant Follow)
        Connection::create([
            'user_id' => $currentUserId,
            'connected_user_id' => $user->id,
            'status' => 'accepted',
        ]);

        return response()->json(['success' => true, 'status' => 'followed', 'message' => 'Follow request sent.']);
    }

    /**
     * Get connection status for a user.
     */
    public function status(User $user)
    {
        $currentUserId = Auth::id();
        $connection = Connection::where(function($q) use ($currentUserId, $user) {
            $q->where('user_id', $currentUserId)->where('connected_user_id', $user->id);
        })->orWhere(function($q) use ($currentUserId, $user) {
            $q->where('user_id', $user->id)->where('connected_user_id', $currentUserId);
        })->first();

        return response()->json([
            'connected' => $connection ? true : false,
            'status' => $connection->status ?? null,
            'is_requester' => $connection ? ($connection->user_id === $currentUserId) : false,
        ]);
    }
}
