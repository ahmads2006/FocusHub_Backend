<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Block;
use App\Models\User;
use App\Models\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockController extends Controller
{
    /**
     * Block a user.
     */
    public function block(User $user): JsonResponse
    {
        $senderId = Auth::id();
        if ($senderId === $user->id) {
            return response()->json(['success' => false, 'message' => 'You cannot block yourself'], 400);
        }

        Block::firstOrCreate([
            'sender_id' => $senderId,
            'blocked_id' => $user->id
        ]);

        // When blocking, we should also remove any existing connection or follows
        Connection::where(function($q) use ($senderId, $user) {
            $q->where('user_id', $senderId)->where('connected_user_id', $user->id);
        })->orWhere(function($q) use ($senderId, $user) {
            $q->where('user_id', $user->id)->where('connected_user_id', $senderId);
        })->delete();

        return response()->json([
            'success' => true,
            'message' => 'User blocked successfully'
        ]);
    }

    /**
     * Unblock a user.
     */
    public function unblock(User $user): JsonResponse
    {
        $senderId = Auth::id();
        Block::where('sender_id', $senderId)
            ->where('blocked_id', $user->id)
            ->delete();

        // Restore the connection so chat works again (block() deletes it)
        Connection::firstOrCreate(
            [
                'user_id' => $senderId,
                'connected_user_id' => $user->id,
            ],
            [
                'status' => 'accepted',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'User unblocked successfully'
        ]);
    }

    /**
     * List blocked users.
     */
    public function index(): JsonResponse
    {
        $blockedUsers = Block::where('sender_id', Auth::id())
            ->with('blocked.profile')
            ->get()
            ->map(fn($b) => [
                'id' => $b->blocked->id,
                'name' => $b->blocked->name,
                'username' => $b->blocked->profile?->username,
                'avatar' => $b->blocked->avatar,
            ]);

        return response()->json([
            'success' => true,
            'data' => $blockedUsers
        ]);
    }
}
