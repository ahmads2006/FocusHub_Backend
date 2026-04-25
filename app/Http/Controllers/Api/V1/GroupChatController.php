<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\GroupMessageSent;
use App\Models\Conversation;
use App\Models\Image;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Services\Core\AssetDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class GroupChatController extends Controller
{
    /**
     * List all group conversations the user participates in.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $groups = Conversation::where('type', 'group')
            ->whereHas('participants', fn($q) => $q->where('users.id', $userId))
            ->with(['participants' => fn($q) => $q->select('users.id', 'users.name')])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $conv) use ($userId) {
                $lastMessage = $conv->messages()->latest()->first();

                return [
                    'id'           => $conv->id,
                    'name'         => $conv->name,
                    'album_id'     => $conv->album_id,
                    'type'         => 'group',
                    'participants' => $conv->participants->map(fn($p) => [
                        'id'     => $p->id,
                        'name'   => $p->name,
                        'avatar' => $p->avatar,
                        'role'   => $p->pivot->role,
                    ]),
                    'last_message' => $lastMessage ? [
                        'body'       => $lastMessage->image_id ? '📷 Shared an image' : $lastMessage->body,
                        'created_at' => $lastMessage->created_at->diffForHumans(),
                        'sender'     => $lastMessage->sender?->name,
                        'is_mine'    => $lastMessage->sender_id === $userId,
                    ] : null,
                    'unread_count' => $conv->unreadCountFor($userId),
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }

    /**
     * Show messages for a specific group conversation.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        if (!$conversation->hasParticipant($userId)) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        // Mark as read
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);

        $messages = $conversation->messages()
            ->with(['sender', 'image.storage', 'image.settings'])
            ->orderBy('created_at', 'asc')
            ->take(100)
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'success' => true,
            'data'    => [
                'conversation' => [
                    'id'       => $conversation->id,
                    'name'     => $conversation->name,
                    'album_id' => $conversation->album_id,
                    'type'     => $conversation->type,
                ],
                'participants' => $conversation->participants->map(fn($p) => [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'avatar'    => $p->avatar,
                    'role'      => $p->pivot->role,
                    'is_online' => (function() use ($p) {
                        try {
                            return (bool) Redis::exists('user:online:' . $p->id);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })(),
                ]),
                'messages' => $messages,
            ],
        ]);
    }

    /**
     * Create a new group conversation manually.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'participant_ids' => 'required|array|min:2|max:50',
            'participant_ids.*' => 'uuid|exists:users,id',
        ]);

        $userId = Auth::id();

        // Ensure creator is not in the participant list
        $participantIds = collect($request->participant_ids)->reject(fn($id) => $id === $userId)->unique()->values();

        if ($participantIds->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => __('chat.group_min_members'),
            ], 422);
        }

        // 🛡️ SECURITY: Verify all added participants are accepted connections
        foreach ($participantIds as $pid) {
            if (!$this->isAcceptedConnection($userId, $pid)) {
                return response()->json([
                    'success' => false,
                    'message' => __('chat.only_add_connections'),
                ], 403);
            }
        }

        $conversation = DB::transaction(function () use ($userId, $participantIds, $request) {
            $conversation = Conversation::create([
                'type'         => 'group',
                'name'         => $request->name,
                'is_name_custom' => true,
                'created_by'   => $userId,
                'last_message_at' => now(),
            ]);

            // Add creator as owner
            $conversation->participants()->attach($userId, [
                'role'      => 'owner',
                'joined_at' => now(),
            ]);

            // Add other participants as members
            foreach ($participantIds as $participantId) {
                $conversation->participants()->attach($participantId, [
                    'role'      => 'member',
                    'joined_at' => now(),
                ]);
            }

            // System message
            Message::create([
                'sender_id'       => $userId,
                'receiver_id'     => $userId, // Self-reference for system messages
                'conversation_id' => $conversation->id,
                'body'            => '📣 تم إنشاء المجموعة.',
            ]);

            return $conversation;
        });

        return response()->json([
            'success' => true,
            'message' => __('chat.group_created'),
            'data'    => [
                'id'   => $conversation->id,
                'name' => $conversation->name,
            ],
        ], 201);
    }

    /**
     * Send a message to a group conversation.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'body'     => 'nullable|string|max:2000',
            'image_id' => 'nullable|uuid|exists:images,id',
        ]);

        if (!$request->body && !$request->image_id) {
            return response()->json(['success' => false, 'message' => __('chat.message_empty')], 422);
        }

        $userId = Auth::id();

        if (!$conversation->hasParticipant($userId)) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        if ($request->image_id) {
            $image = Image::find($request->image_id);
            if ($image->user_id !== $userId) {
                return response()->json(['success' => false, 'message' => __('chat.only_share_own_photos')], 403);
            }
        }

        $message = Message::create([
            'sender_id'       => $userId,
            'receiver_id'     => $userId, // Self-reference for group messages
            'conversation_id' => $conversation->id,
            'image_id'        => $request->image_id,
            'body'            => $request->body,
        ]);

        // Update conversation's last_message_at for efficient ordering
        $conversation->update(['last_message_at' => now()]);

        // Broadcast to all participants
        try {
            event(new GroupMessageSent($message->load(['sender', 'image'])));
        } catch (\Throwable $e) {}

        // Notify other participants via push notification
        $otherParticipants = $conversation->participants()->where('users.id', '!=', $userId)->get();
        $sender = Auth::user();
        foreach ($otherParticipants as $participant) {
            try {
                $participant->notify(new ChatMessageNotification($sender, $request->body ?? 'Shared an image'));
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $userId),
        ], 201);
    }

    /**
     * Poll for new messages in a group conversation.
     */
    public function poll(Conversation $conversation, Request $request): JsonResponse
    {
        $userId  = Auth::id();

        if (!$conversation->hasParticipant($userId)) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        $afterId = $request->query('after_id', 0);

        $newMessages = $conversation->messages()
            ->where('id', '>', $afterId)
            ->with(['sender', 'image.storage', 'image.settings'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        if ($newMessages->isNotEmpty()) {
            $conversation->participants()->updateExistingPivot($userId, [
                'last_read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $newMessages,
            ],
        ]);
    }

    /**
     * Add a participant to a group conversation.
     */
    public function addParticipant(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        // Only owner can add participants
        $participant = $conversation->participants()->where('users.id', $userId)->first();
        if (!$participant || $participant->pivot->role !== 'owner') {
            return response()->json(['success' => false, 'message' => __('chat.only_owner_can_add')], 403);
        }

        $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        if ($conversation->hasParticipant($request->user_id)) {
            return response()->json(['success' => false, 'message' => __('chat.user_already_member')], 409);
        }

        // 🛡️ SECURITY: Verify the added participant is an accepted connection
        if (!$this->isAcceptedConnection($userId, $request->user_id)) {
            return response()->json([
                'success' => false,
                'message' => __('chat.only_add_connections'),
            ], 403);
        }

        $userToAdd = User::find($request->user_id);

        $conversation->participants()->attach($request->user_id, [
            'role'      => 'member',
            'joined_at' => now(),
        ]);

        // System message
        Message::create([
            'sender_id'       => $userId,
            'receiver_id'     => $userId,
            'conversation_id' => $conversation->id,
            'body'            => __('chat.system_member_added', ['name' => $userToAdd->name]),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => __('chat.member_added', ['name' => $userToAdd->name]),
        ]);
    }

    /**
     * Remove a participant from a group conversation.
     */
    public function removeParticipant(Conversation $conversation, User $user): JsonResponse
    {
        $userId = Auth::id();

        // Only owner can remove, or user can remove themselves (leave)
        $actor = $conversation->participants()->where('users.id', $userId)->first();
        if (!$actor) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        $isOwner = $actor->pivot->role === 'owner';
        $isSelf  = $userId === $user->id;

        if (!$isOwner && !$isSelf) {
            return response()->json(['success' => false, 'message' => __('chat.only_owner_can_remove')], 403);
        }

        // Owner cannot be removed
        $targetParticipant = $conversation->participants()->where('users.id', $user->id)->first();
        if ($targetParticipant && $targetParticipant->pivot->role === 'owner' && !$isSelf) {
            return response()->json(['success' => false, 'message' => __('chat.cannot_remove_owner')], 400);
        }

        $conversation->participants()->detach($user->id);

        // System message
        $actionText = $isSelf ? __('chat.system_member_left', ['name' => $user->name]) : __('chat.system_member_removed', ['name' => $user->name]);
        Message::create([
            'sender_id'       => $userId,
            'receiver_id'     => $userId,
            'conversation_id' => $conversation->id,
            'body'            => $actionText,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => $isSelf ? __('chat.left_group') : __('chat.member_removed', ['name' => $user->name]),
        ]);
    }

    /**
     * Rename a group conversation.
     */
    public function rename(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        $participant = $conversation->participants()->where('users.id', $userId)->first();
        if (!$participant || $participant->pivot->role !== 'owner') {
            return response()->json(['success' => false, 'message' => __('chat.only_owner_can_rename')], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $conversation->update([
            'name'           => $request->name,
            'is_name_custom' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('chat.group_renamed'),
            'data'    => ['name' => $conversation->name],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────

    private function formatMessage(Message $msg, string $userId): array
    {
        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'body'            => $msg->body,
            'image_id'        => $msg->image_id,
            'image_url'       => $msg->image_id ? $msg->image?->url : null,
            'thumb_url'       => $msg->image_id ? app(AssetDeliveryService::class)->getUrl($msg->image, 'thumbnail') : null,
            'is_mine'         => $msg->sender_id === $userId,
            'sender'          => [
                'id'     => $msg->sender?->id,
                'name'   => $msg->sender?->name,
                'avatar' => $msg->sender?->avatar,
            ],
            'created_at'      => $msg->created_at->format('H:i'),
            'date'            => $msg->created_at->format('Y-m-d'),
        ];
    }

    private function isAcceptedConnection(string $userId, string $partnerId): bool
    {
        return DB::table('connections')
            ->where('status', 'accepted')
            ->where(function ($q) use ($userId, $partnerId) {
                $q->where(function ($inner) use ($userId, $partnerId) {
                    $inner->where('user_id', $userId)->where('connected_user_id', $partnerId);
                })->orWhere(function ($inner) use ($userId, $partnerId) {
                    $inner->where('user_id', $partnerId)->where('connected_user_id', $userId);
                });
            })
            ->exists();
    }
}
