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

class ChatController extends Controller
{
    /**
     * Get all conversation threads for the current user.
     */
    public function conversations(): JsonResponse
    {
        $userId = Auth::id();

        $latestMessages = DB::table('messages')
            ->select(DB::raw('
                CASE
                    WHEN sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as partner_id
            '), DB::raw('MAX(id) as latest_message_id'))
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->groupBy('partner_id')
            ->setBindings([$userId])
            ->get();

        $conversations = [];
        foreach ($latestMessages as $row) {
            $message = Message::with(['sender:id', 'receiver:id'])->find($row->latest_message_id);
            if (!$message) continue;

            $partner = User::with('profile')->find($row->partner_id);
            if (!$partner) continue;

            $unreadCount = Message::where('sender_id', $row->partner_id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->count();

            $conversations[] = [
                'partner' => [
                    'id'     => $partner->id,
                    'name'   => $partner->name,
                    'avatar' => $partner->avatar,
                ],
                'last_message' => [
                    'body'       => $message->image_id ? '📷 Shared an image' : $message->body,
                    'created_at' => $message->created_at->diffForHumans(),
                    'is_mine'    => $message->sender_id === $userId,
                ],
                'unread_count' => $unreadCount,
                'is_online'    => (bool) Redis::exists('user:online:' . $row->partner_id),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => $conversations,
        ]);
    }

    /**
     * Get message history with a partner.
     */
    public function messages(User $partner): JsonResponse
    {
        $userId = Auth::id();

        if (!$this->isAcceptedConnection($userId, $partner->id)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        // Mark messages as read
        Message::where('sender_id', $partner->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::conversation($userId, $partner->id)
            ->with(['image.storage', 'image.settings'])
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        return response()->json([
            'success' => true,
            'data'    => [
                'messages' => $messages,
                'partner'  => [
                    'id'        => $partner->id,
                    'name'      => $partner->name,
                    'avatar'    => $partner->avatar,
                    'is_online' => (bool) Redis::exists('user:online:' . $partner->id),
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
            'receiver_id' => 'required|uuid|exists:users,id',
            'body'        => 'nullable|string|max:2000',
            'image_id'    => 'nullable|uuid|exists:images,id',
        ]);

        if (!$request->body && !$request->image_id) {
            return response()->json(['success' => false, 'message' => 'الرسالة لا يمكن أن تكون فارغة.'], 422);
        }

        $userId = Auth::id();

        if (!$this->isAcceptedConnection($userId, $request->receiver_id)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح.'], 403);
        }

        if ($request->image_id) {
            $image = Image::find($request->image_id);
            if ($image->user_id !== $userId) {
                return response()->json(['success' => false, 'message' => 'يمكنك مشاركة صورك فقط.'], 403);
            }
        }

        $message = Message::create([
            'sender_id'   => $userId,
            'receiver_id' => $request->receiver_id,
            'image_id'    => $request->image_id,
            'body'        => $request->body,
        ]);

        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $receiver->notify(new ChatMessageNotification(Auth::user(), $request->body ?? 'Shared an image'));
        }

        try {
            event(new MessageSent($message->load(['sender', 'image'])));
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'data'    => $this->formatMessage($message, $userId),
        ], 201);
    }

    /**
     * Poll for new messages from a partner.
     */
    public function poll(User $partner, Request $request): JsonResponse
    {
        $userId  = Auth::id();
        $afterId = $request->query('after_id', 0);

        $newMessages = Message::conversation($userId, $partner->id)
            ->where('id', '>', $afterId)
            ->with(['image.storage', 'image.settings'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($msg) => $this->formatMessage($msg, $userId));

        if ($newMessages->isNotEmpty()) {
            Message::where('sender_id', $partner->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'messages'  => $newMessages,
                'is_online' => (bool) Redis::exists('user:online:' . $partner->id),
            ],
        ]);
    }

    /**
     * Get unread message count.
     */
    public function unreadCount(): JsonResponse
    {
        $count = Message::unreadFor(Auth::id())->count();
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

    private function formatMessage(Message $msg, string $userId): array
    {
        return [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'image_id'   => $msg->image_id,
            'album_id'   => $msg->album_id,
            'image_url'  => $msg->image_id ? $msg->image?->url : null,
            'thumb_url'  => $msg->image_id ? app(AssetDeliveryService::class)->getUrl($msg->image, 'thumbnail') : null,
            'is_mine'    => $msg->sender_id === $userId,
            'is_read'    => $msg->is_read,
            'created_at' => $msg->created_at->format('H:i'),
            'date'       => $msg->created_at->format('Y-m-d'),
        ];
    }

    private function isAcceptedConnection(string $userId, string $partnerId): bool
    {
        return DB::table('connections')
            ->where(function ($q) use ($userId, $partnerId) {
                $q->where('user_id', $userId)->where('connected_user_id', $partnerId);
            })
            ->orWhere(function ($q) use ($userId, $partnerId) {
                $q->where('user_id', $partnerId)->where('connected_user_id', $userId);
            })
            ->where('status', 'accepted')
            ->exists();
    }
}
