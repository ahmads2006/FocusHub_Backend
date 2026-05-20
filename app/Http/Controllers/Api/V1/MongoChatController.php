<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Events\MessageSent;
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
use App\Models\Mongo\ChatMessage as MongoMessage;
use App\Services\MongoDBService;

class MongoChatController extends Controller
{
    protected MongoDBService $mongoService;

    public function __construct(MongoDBService $mongoService)
    {
        $this->mongoService = $mongoService;
    }
    /**
     * Get all conversation threads for the current user.
     */
    /**
     * Get all conversation threads for the current user.
     */
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        // 1. Get all connections first (including pending ones)
        $connections = DB::table('connections')
            ->where('user_id', $userId)
            ->orWhere('connected_user_id', $userId)
            ->get();

        $conversations = [];
        foreach ($connections as $conn) {
            $partnerId = ($conn->user_id == $userId) ? $conn->connected_user_id : $conn->user_id;
            $partner = User::with('profile')->find($partnerId);
            if (!$partner) continue;

            // Get latest message from MongoDB
            $latestMessage = MongoMessage::where(function($q) use ($userId, $partnerId) {
                $q->where('sender_id', $userId)->where('receiver_id', $partnerId);
            })->orWhere(function($q) use ($userId, $partnerId) {
                $q->where('sender_id', $partnerId)->where('receiver_id', $userId);
            })
            ->whereNull('conversation_id')
            ->orderBy('created_at', 'desc')
            ->first();

            // Skip if no message and it's not a pending request where I am the receiver
            if (!$latestMessage && $conn->status === 'accepted') continue;

            $unreadCount = MongoMessage::where('sender_id', $partnerId)
                ->where('receiver_id', $userId)
                ->whereNull('conversation_id')
                ->where('is_read', false)
                ->count();

            $conversations[] = [
                'type'    => 'direct',
                'partner' => [
                    'id'     => $partner->id,
                    'name'   => $partner->profile?->name ?? $partner->name,
                    'avatar' => $partner->avatar,
                ],
                'status' => $conn->status,
                'is_requester' => $conn->user_id == $userId,
                'last_message' => $latestMessage ? [
                    'body'       => $latestMessage->image_id ? '📷 Shared an image' : $latestMessage->body,
                    'created_at' => \Carbon\Carbon::parse($latestMessage->created_at)->diffForHumans(),
                    'is_mine'    => $latestMessage->sender_id == $userId,
                ] : [
                    'body' => 'طلب مراسلة جديد',
                    'created_at' => \Carbon\Carbon::parse($conn->created_at)->diffForHumans(),
                    'is_mine' => $conn->user_id == $userId,
                ],
                'last_message_at' => $latestMessage ? $latestMessage->created_at : $conn->created_at,
                'unread_count' => $unreadCount,
                'is_online'    => (function() use ($partnerId) {
                    try {
                        return (bool) Redis::exists('user:online:' . $partnerId);
                    } catch (\Exception $e) {
                        return false;
                    }
                })(),
            ];
        }



        // ── 2. Group conversations ────────────────────────────
        $groupConversations = \App\Models\Conversation::where('type', 'group')
            ->whereHas('participants', fn($q) => $q->where('users.id', $userId))
            ->with(['participants' => fn($q) => $q->with('profile')])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (\App\Models\Conversation $conv) use ($userId) {
                $lastMessage = $conv->messages()->latest()->first();

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
                        'is_mine'    => $lastMessage->sender_id === $userId,
                    ] : null,
                    'last_message_at' => $lastMessage?->created_at?->toISOString() ?? $conv->created_at->toISOString(),
                    'unread_count' => $conv->unreadCountFor($userId),
                ];
            })->toArray();

        // ── 3. Merge & sort by last_message_at ────────────────
        $unified = collect(array_merge($conversations, $groupConversations))
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
        $userId = Auth::id();

        // Allow reading if connection exists (even if pending)
        $connection = DB::table('connections')
            ->where(function($q) use ($userId, $partner) {
                $q->where('user_id', $userId)->where('connected_user_id', $partner->id);
            })->orWhere(function($q) use ($userId, $partner) {
                $q->where('user_id', $partner->id)->where('connected_user_id', $userId);
            })->first();

        if (!$connection) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        // Mark messages as read directly in Mongo
        MongoMessage::where('sender_id', $partner->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = MongoMessage::whereIn('sender_id', [$userId, $partner->id])
            ->whereIn('receiver_id', [$userId, $partner->id])
            ->whereNull('conversation_id')
            ->orderBy('created_at', 'desc')
            ->orderBy('_id', 'desc')
            ->take(50)
            ->get()
            ->reverse()
            ->values()
            ->map(function($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $messages,
                'status'   => $connection->status,
                'is_requester' => $connection->user_id === $userId,
                'partner'  => [
                    'id'        => $partner->id,
                    'name'      => $partner->name,
                    'avatar'    => $partner->avatar,
                    'is_online' => (function() use ($partner) {
                        try {
                            return (bool) Redis::exists('user:online:' . $partner->id);
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

        $userId = Auth::id();

        // Check or create connection
        $connection = \App\Models\Connection::where(function ($q) use ($userId, $request) {
            $q->where('user_id', $userId)->where('connected_user_id', $request->receiver_id);
        })->orWhere(function ($q) use ($userId, $request) {
            $q->where('user_id', $request->receiver_id)->where('connected_user_id', $userId);
        })->first();

        if (!$connection) {
            // Create pending connection (Message Request)
            $connection = \App\Models\Connection::create([
                'user_id' => $userId,
                'connected_user_id' => $request->receiver_id,
                'status' => 'pending'
            ]);
        }

        $image = null;
        if ($request->image_id) {
            $image = Image::find($request->image_id);
            if ($image->user_id !== $userId) {
                return response()->json(['success' => false, 'message' => __('chat.only_share_own_photos')], 403);
            }
        }

        // Transactional Outbox Pattern to safely push to MongoDB
        $payload = [
            'id'          => (string) \Illuminate\Support\Str::uuid(),
            'sender_id'   => $userId,
            'receiver_id' => $request->receiver_id,
            'image_id'    => $request->image_id,
            'body'        => $request->body,
            'is_read'     => false,
            'created_at'  => now()->toISOString(),
            'updated_at'  => now()->toISOString(),
        ];

        // Denormalize image data slightly for MongoDB performance
        if ($image) {
            $payload['image_url'] = $image->url;
            $payload['thumb_url'] = app(AssetDeliveryService::class)->getUrl($image, 'thumbnail');
        }

        // Insert into outbox (MySQL) which will be processed by worker to insert into MongoDB
        $this->mongoService->outboxInsert('chat_messages', $payload);

        // Treat payload as loaded for frontend response immediately
        $messageModel = new MongoMessage($payload);

        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $prefs = $receiver->notification_preferences ?? [];
            if (($prefs['push_chat'] ?? true) === true) {
                $receiver->notify(new ChatMessageNotification(Auth::user(), $request->body ?? 'Shared an image'));
            }
        }

        try {
            event(new MessageSent($messageModel));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Broadcast error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($messageModel, $userId),
        ], 201);
    }

    /**
     * Poll for new messages from a partner.
     */
    public function poll(User $partner, Request $request): JsonResponse
    {
        $userId  = Auth::id();
        $afterId = $request->query('after_id');
        $afterDate = $request->query('after_date');

        // 🛡️ SECURITY: Prevent unauthorized polling
        if (!$this->isAcceptedConnection($userId, $partner->id)) {
            return response()->json(['success' => false, 'message' => __('chat.unauthorized')], 403);
        }

        $query = MongoMessage::whereIn('sender_id', [$userId, $partner->id])
            ->whereIn('receiver_id', [$userId, $partner->id])
            ->whereNull('conversation_id');

        if ($afterId) {
            $lastMsg = MongoMessage::where('id', $afterId)->first();
            if ($lastMsg) {
                $query->where('created_at', '>', $lastMsg->created_at);
            }
        } elseif ($afterDate) {
            $query->where('created_at', '>', \Carbon\Carbon::parse($afterDate));
        }

        $newMessages = $query->orderBy('created_at', 'asc')
            ->get()
            ->map(function($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            });

        if ($newMessages->isNotEmpty()) {
            MongoMessage::where('sender_id', $partner->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'messages'  => $newMessages,
                'is_online' => (function() use ($partner) {
                    try {
                        return (bool) Redis::exists('user:online:' . $partner->id);
                    } catch (\Exception $e) {
                        return false;
                    }
                })(),
            ],
        ]);
    }

    /**
     * Get unread message count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = MongoMessage::where('receiver_id', Auth::id())
                    ->where('is_read', false)
                    ->count();
        return response()->json(['success' => true, 'data' => ['count' => $count]]);
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
     * Get user's connections for sharing.
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


    // ── Helpers ──

    /**
     * Update a message.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);
        $userId = Auth::id();

        $message = MongoMessage::find($id);
        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        if ($message->sender_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message->update([
            'body'       => $request->body,
            'is_edited'  => true,
            'updated_at' => now()->toISOString()
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
        $userId = Auth::id();
        $message = MongoMessage::find($id);

        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        if ($message->sender_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['success' => true, 'message' => 'Message deleted']);
    }

    /**
     * Accept a message request.
     */
    public function acceptConversation(string $partner): \Illuminate\Http\JsonResponse
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

        // Notify the requester
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
    public function declineConversation(string $partner): \Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();
        $partnerId = $partner;
        
        $connection = \App\Models\Connection::where(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $partnerId)->where('connected_user_id', $userId);
        })->orWhere(function ($q) use ($userId, $partnerId) {
            $q->where('user_id', $userId)->where('connected_user_id', $partnerId);
        })->first();

        if ($connection) {
            // Notify before deleting (polite rejection)
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
        // Safety check if $msg is somehow an array or stdClass (linter defense)
        if (is_array($msg)) {
            $msg = (object) $msg;
        }

        $createdAt = $msg->created_at;
        if (is_string($createdAt)) {
            $createdAt = \Carbon\Carbon::parse($createdAt);
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
            'id'         => $msg->id,
            'body'       => $msg->body,
            'image_id'   => $msg->image_id,
            'album_id'   => $msg->album_id,
            'image_url'  => $imageUrl,
            'thumb_url'  => $thumbUrl,
            'is_mine'    => $msg->sender_id == $userId,
            'is_read'    => (bool) $msg->is_read,
            'created_at' => $createdAt->format('H:i'),
            'date'       => $createdAt->format('Y-m-d'),
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
