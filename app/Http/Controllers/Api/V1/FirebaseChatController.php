<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use App\Models\Image;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use App\Services\Core\AssetDeliveryService;
use App\Services\Firebase\FirestoreService;
use App\Models\Firebase\ChatMessage as FirebaseMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class FirebaseChatController extends Controller
{
    protected FirestoreService $firestore;

    public function __construct(FirestoreService $firestore)
    {
        $this->firestore = $firestore;
    }

    /**
     * Get all conversation threads for the current user.
     */
    public function conversations(): JsonResponse
    {
        $userId = (string) Auth::id();

        // 1. Get all connections from MySQL
        $connections = DB::table('connections')
            ->where('user_id', $userId)
            ->orWhere('connected_user_id', $userId)
            ->get();

        $partnerIds = [];
        foreach ($connections as $conn) {
            $partnerIds[] = ($conn->user_id == $userId) ? (string) $conn->connected_user_id : (string) $conn->user_id;
        }
        $partnerIds = array_values(array_unique($partnerIds));

        $partners = User::with('profile')->whereIn('id', $partnerIds)->get()->keyBy('id');

        $latestMessagesMap = [];
        $unreadCountsMap = [];

        // Fetch user's chat messages from Firestore
        if (!empty($partnerIds)) {
            $sentMessages = [];
            $receivedMessages = [];

            try {
                $sentMessages = $this->firestore->runQuery('chat_messages', [
                    ['field' => 'sender_id', 'op' => 'EQUAL', 'value' => $userId],
                ], [], 100);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Error fetching Firestore sent messages', [
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $receivedMessages = $this->firestore->runQuery('chat_messages', [
                    ['field' => 'receiver_id', 'op' => 'EQUAL', 'value' => $userId],
                ], [], 100);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Error fetching Firestore received messages', [
                    'error' => $e->getMessage(),
                ]);
            }

            $allMessages = array_merge($sentMessages, $receivedMessages);

            // Sort descending by created_at
            usort($allMessages, function ($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });

            foreach ($allMessages as $msg) {
                $sender = (string) ($msg['sender_id'] ?? '');
                $receiver = (string) ($msg['receiver_id'] ?? '');
                $partnerId = ($sender === $userId) ? $receiver : $sender;

                if (!in_array($partnerId, $partnerIds)) {
                    continue;
                }

                // Keep the latest message for this partner
                if (!isset($latestMessagesMap[$partnerId])) {
                    $latestMessagesMap[$partnerId] = new FirebaseMessage($msg);
                }

                // Count unread if I am receiver
                if ($receiver === $userId && empty($msg['is_read'])) {
                    $unreadCountsMap[$partnerId] = ($unreadCountsMap[$partnerId] ?? 0) + 1;
                }
            }
        }

        $conversations = [];
        foreach ($connections as $conn) {
            $partnerId = ($conn->user_id == $userId) ? (string) $conn->connected_user_id : (string) $conn->user_id;
            $partner = $partners->get($partnerId);
            if (!$partner) continue;

            $latestMessage = $latestMessagesMap[$partnerId] ?? null;
            $unreadCount = $unreadCountsMap[$partnerId] ?? 0;

            $conversations[] = [
                'type'    => 'direct',
                'partner' => [
                    'id'     => $partner->id,
                    'name'   => $partner->profile?->name ?? $partner->name,
                    'avatar' => $partner->avatar,
                ],
                'status' => $conn->status,
                'is_requester' => (string) $conn->user_id === $userId,
                'last_message' => $latestMessage ? [
                    'body'       => $latestMessage->image_id ? '📷 Shared an image' : $latestMessage->body,
                    'created_at' => Carbon::parse($latestMessage->created_at)->diffForHumans(),
                    'is_mine'    => (string) $latestMessage->sender_id === $userId,
                ] : [
                    'body'       => $conn->status === 'accepted' ? 'محادثة جديدة' : 'طلب مراسلة جديد',
                    'created_at' => Carbon::parse($conn->created_at)->diffForHumans(),
                    'is_mine'    => (string) $conn->user_id === $userId,
                ],
                'last_message_at' => $latestMessage ? $latestMessage->created_at : $conn->created_at,
                'unread_count' => $unreadCount,
                'is_online'    => (function () use ($partnerId) {
                    try {
                        return (bool) Redis::exists('user:online:' . $partnerId);
                    } catch (\Exception $e) {
                        return false;
                    }
                })(),
            ];
        }

        // 2. Group conversations (from MySQL)
        $groupConversations = \App\Models\Conversation::where('type', 'group')
            ->whereHas('participants', fn($q) => $q->where('users.id', $userId))
            ->with([
                'participants' => fn($q) => $q->with('profile'),
                'latestMessage' => fn($q) => $q->with(['sender', 'image'])
            ])
            ->orderByDesc('last_message_at')
            ->get();

        $groupConversationsFormatted = $groupConversations->map(function (\App\Models\Conversation $conv) use ($userId) {
            $lastMessage = $conv->latestMessage;

            return [
                'type'         => 'group',
                'id'           => $conv->id,
                'name'         => $conv->name,
                'album_id'     => $conv->album_id,
                'participants' => $conv->participants->map(fn($p) => [
                    'id'     => $p->id,
                    'name'   => $p->profile?->name ?? $p->name,
                    'avatar' => $p->avatar,
                ]),
                'last_message' => $lastMessage ? [
                    'body'       => $lastMessage->image_id ? '📷 Shared an image' : $lastMessage->body,
                    'created_at' => $lastMessage->created_at->diffForHumans(),
                    'sender'     => $lastMessage->sender?->name,
                    'is_mine'    => (string) $lastMessage->sender_id === $userId,
                ] : null,
                'last_message_at' => $lastMessage?->created_at?->toISOString() ?? $conv->created_at->toISOString(),
                'unread_count' => 0,
            ];
        })->toArray();

        // 3. Merge and sort
        $unified = collect(array_merge($conversations, $groupConversationsFormatted))
            ->sortByDesc('last_message_at')
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data'    => $unified,
        ]);
    }

    /**
     * Get message history with a partner.
     */
    public function messages(User $partner): JsonResponse
    {
        $userId = (string) Auth::id();
        $partnerId = (string) $partner->id;

        // Verify connection exists
        $connection = DB::table('connections')
            ->where(function ($q) use ($userId, $partnerId) {
                $q->where('user_id', $userId)->where('connected_user_id', $partnerId);
            })->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('user_id', $partnerId)->where('connected_user_id', $userId);
            })->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        $messages = [];

        try {
            // Fetch messages from Firestore: Sent by user to partner
            $sent = [];
            try {
                $sent = $this->firestore->runQuery('chat_messages', [
                    ['field' => 'sender_id', 'op' => 'EQUAL', 'value' => $userId],
                    ['field' => 'receiver_id', 'op' => 'EQUAL', 'value' => $partnerId],
                ], [], 100);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Error fetching sent messages: ' . $e->getMessage());
            }

            // Received from partner
            $received = [];
            try {
                $received = $this->firestore->runQuery('chat_messages', [
                    ['field' => 'sender_id', 'op' => 'EQUAL', 'value' => $partnerId],
                    ['field' => 'receiver_id', 'op' => 'EQUAL', 'value' => $userId],
                ], [], 100);
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Error fetching received messages: ' . $e->getMessage());
            }

            $combined = array_merge($sent, $received);

            // Mark unread received messages as read
            foreach ($received as $item) {
                if (empty($item['is_read'])) {
                    $docId = $item['id'] ?? null;
                    if ($docId) {
                        $this->firestore->setDocument('chat_messages', $docId, array_merge($item, ['is_read' => true]));
                    }
                }
            }

            // Sort ascending for chat flow
            usort($combined, function ($a, $b) {
                return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            });

            // Keep latest 50 messages
            if (count($combined) > 50) {
                $combined = array_slice($combined, -50);
            }

            $messages = array_map(function ($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            }, $combined);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Error loading Firestore messages', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'messages'     => $messages,
                'status'       => $connection->status,
                'is_requester' => (string) $connection->user_id === $userId,
                'partner'      => [
                    'id'        => $partner->id,
                    'name'      => $partner->name,
                    'avatar'    => $partner->avatar,
                    'is_online' => (function () use ($partnerId) {
                        try {
                            return (bool) Redis::exists('user:online:' . $partnerId);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })(),
                ],
            ],
        ]);
    }

    /**
     * Send a message (text or image).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'body'        => 'nullable|string|max:2000',
            'image_id'    => 'nullable|exists:images,id',
        ]);

        if (!$request->body && !$request->image_id) {
            return response()->json(['success' => false, 'message' => __('chat.message_empty')], 422);
        }

        $userId = (string) Auth::id();
        $receiverId = (string) $request->receiver_id;

        // Check or create connection
        $connection = \App\Models\Connection::where(function ($q) use ($userId, $receiverId) {
            $q->where('user_id', $userId)->where('connected_user_id', $receiverId);
        })->orWhere(function ($q) use ($userId, $receiverId) {
            $q->where('user_id', $receiverId)->where('connected_user_id', $userId);
        })->first();

        if (!$connection) {
            $connection = \App\Models\Connection::create([
                'user_id'           => $userId,
                'connected_user_id' => $receiverId,
                'status'            => 'pending',
            ]);
        }

        $image = null;
        if ($request->image_id) {
            $image = Image::withoutGlobalScopes()->find($request->image_id);
            if ($image && (string) $image->user_id !== $userId) {
                return response()->json(['success' => false, 'message' => __('chat.only_share_own_photos')], 403);
            }
        }

        $uuid = (string) Str::uuid();
        $payload = [
            'id'          => $uuid,
            '_id'         => $uuid,
            'sender_id'   => $userId,
            'receiver_id' => $receiverId,
            'image_id'    => $request->image_id,
            'body'        => $request->body,
            'is_read'     => false,
            'created_at'  => now()->toISOString(),
            'updated_at'  => now()->toISOString(),
        ];

        if ($image) {
            $payload['image_url'] = $image->url;
            $payload['thumb_url'] = app(AssetDeliveryService::class)->getUrl($image, 'thumbnail');
        }

        // Insert into Firestore (with Outbox fallback on network issues)
        $this->firestore->directInsert('chat_messages', $payload);

        $messageModel = new FirebaseMessage($payload);

        // Notify Receiver
        $receiver = User::find($receiverId);
        if ($receiver) {
            $prefs = $receiver->notification_preferences ?? [];
            if (($prefs['push_chat'] ?? true) === true) {
                $receiver->notify(new ChatMessageNotification(Auth::user(), $request->body ?? 'Shared an image'));
            }
        }

        // Broadcast realtime WebSocket event
        try {
            $authUser = Auth::user();
            event(new MessageSent($messageModel, [
                'name'   => $authUser?->name ?? 'User',
                'avatar' => $authUser?->avatar ?? null,
            ]));
        } catch (\Throwable $e) {
            Log::error('Broadcast error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($messageModel, $userId),
        ], 201);
    }

    /**
     * Poll for new messages.
     */
    public function poll(User $partner, Request $request): JsonResponse
    {
        $userId = (string) Auth::id();
        $partnerId = (string) $partner->id;
        $afterDate = $request->query('after_date');

        try {
            $filters = [
                ['field' => 'sender_id', 'op' => 'EQUAL', 'value' => $partnerId],
                ['field' => 'receiver_id', 'op' => 'EQUAL', 'value' => $userId],
            ];

            if ($afterDate) {
                $filters[] = ['field' => 'created_at', 'op' => 'GREATER_THAN', 'value' => $afterDate];
            }

            $messages = $this->firestore->runQuery('chat_messages', $filters, [], 50);

            // Sort ascending for chronological delivery
            usort($messages, function ($a, $b) {
                return strcmp($a['created_at'] ?? '', $b['created_at'] ?? '');
            });

            // Mark as read
            foreach ($messages as $msg) {
                if (empty($msg['is_read']) && !empty($msg['id'])) {
                    $this->firestore->setDocument('chat_messages', $msg['id'], array_merge($msg, ['is_read' => true]));
                }
            }

            $formatted = array_map(function ($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            }, $messages);

            return response()->json([
                'success' => true,
                'data'    => [
                    'messages'  => $formatted,
                    'is_online' => (bool) Redis::exists('user:online:' . $partnerId),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => true,
                'data'    => ['messages' => [], 'is_online' => false],
            ]);
        }
    }

    /**
     * Get unread message count.
     */
    public function unreadCount(): JsonResponse
    {
        $userId = (string) Auth::id();

        try {
            $unread = $this->firestore->runQuery('chat_messages', [
                ['field' => 'receiver_id', 'op' => 'EQUAL', 'value' => $userId],
                ['field' => 'is_read', 'op' => 'EQUAL', 'value' => false],
            ], [], 100);

            return response()->json(['success' => true, 'data' => ['count' => count($unread)]]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'data' => ['count' => 0]]);
        }
    }

    /**
     * Get user's images for the media picker.
     */
    public function myImages(): JsonResponse
    {
        $images = Auth::user()->images()
            ->with(['storage', 'settings'])
            ->latest()
            ->paginate(12)
            ->through(fn($img) => [
                'id'    => $img->id,
                'url'   => $img->url,
                'thumb' => app(AssetDeliveryService::class)->getUrl($img, 'thumbnail'),
                'title' => $img->title,
            ]);

        return response()->json(['success' => true, 'data' => $images]);
    }

    /**
     * Get user's connections.
     */
    public function connections(): JsonResponse
    {
        $connections = Auth::user()->acceptedConnections()
            ->with(['profile'])
            ->get()
            ->map(fn($conn) => [
                'id'     => $conn->id,
                'name'   => $conn->name,
                'avatar' => $conn->avatar,
            ]);

        return response()->json(['success' => true, 'data' => $connections]);
    }

    /**
     * Update a message.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);
        $userId = (string) Auth::id();

        $message = FirebaseMessage::find($id);
        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        if ((string) $message->sender_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message->update([
            'body'       => $request->body,
            'is_edited'  => true,
            'updated_at' => now()->toISOString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $userId),
        ]);
    }

    /**
     * Delete a message.
     */
    public function destroy(string $id): JsonResponse
    {
        $userId = (string) Auth::id();
        $message = FirebaseMessage::find($id);

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        if ((string) $message->sender_id !== $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['success' => true, 'message' => 'Message deleted']);
    }

    /**
     * Accept a message request.
     */
    public function acceptConversation(string $partner): JsonResponse
    {
        $userId = Auth::id();
        $partnerId = $partner;

        $connection = \App\Models\Connection::where(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $partnerId)->where('connected_user_id', $userId);
        })->orWhere(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $userId)->where('connected_user_id', $partnerId);
        })->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }

        $connection->update(['status' => 'accepted']);

        $requesterId = ($connection->user_id == $userId) ? $connection->connected_user_id : $connection->user_id;
        $requester = \App\Models\User::find($requesterId);
        if ($requester) {
            $requester->notify(new \App\Notifications\ChatRequestStatusNotification(Auth::user(), 'accepted'));
        }

        return response()->json(['success' => true, 'message' => 'Conversation accepted']);
    }

    /**
     * Decline a message request.
     */
    public function declineConversation(string $partner): JsonResponse
    {
        $userId = Auth::id();
        $partnerId = $partner;

        $connection = \App\Models\Connection::where(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $partnerId)->where('connected_user_id', $userId);
        })->orWhere(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $userId)->where('connected_user_id', $partnerId);
        })->first();

        if ($connection) {
            $requesterId = ($connection->user_id == $userId) ? $connection->connected_user_id : $connection->user_id;
            $requester = \App\Models\User::find($requesterId);
            if ($requester) {
                $requester->notify(new \App\Notifications\ChatRequestStatusNotification(Auth::user(), 'declined'));
            }

            $connection->delete();
        }

        return response()->json(['success' => true, 'message' => 'Conversation declined']);
    }

    private function formatMessage($msg, string $userId): array
    {
        if (is_array($msg)) {
            $msg = (object) $msg;
        }

        $createdAt = $msg->created_at ?? now()->toISOString();
        if (is_string($createdAt)) {
            $createdAt = Carbon::parse($createdAt);
        }

        $imageUrl = null;
        $thumbUrl = null;

        if (!empty($msg->image_id)) {
            $image = \App\Models\Image::find($msg->image_id);
            $imageUrl = $image?->url ?? $msg->image_url ?? null;
            if ($image) {
                try {
                    $thumbUrl = app(AssetDeliveryService::class)->getUrl($image, 'thumbnail');
                } catch (\Throwable $e) {
                    $thumbUrl = $msg->thumb_url ?? $imageUrl;
                }
            } else {
                $thumbUrl = $msg->thumb_url ?? $imageUrl;
            }
        }

        return [
            'id'         => $msg->id ?? $msg->_id ?? null,
            'body'       => $msg->body ?? null,
            'image_id'   => $msg->image_id ?? null,
            'album_id'   => $msg->album_id ?? null,
            'image_url'  => $imageUrl,
            'thumb_url'  => $thumbUrl,
            'is_mine'    => (string) ($msg->sender_id ?? '') === (string) $userId,
            'is_read'    => (bool) ($msg->is_read ?? false),
            'created_at' => $createdAt instanceof Carbon ? $createdAt->format('H:i') : '',
            'date'       => $createdAt instanceof Carbon ? $createdAt->format('Y-m-d') : '',
        ];
    }
}
