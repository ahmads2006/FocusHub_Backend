<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\Image;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Notifications\ChatMessageNotification;

class ChatController extends Controller
{
    /**
     * Render the Photographers Hub page.
     * Shows only accepted connections with online status.
     */
    public function hub(?string $partnerId = null)
    {
        $user = Auth::user();

        // Get all accepted connections using the existing User method
        $connections = $user->acceptedConnections()
            ->with('profile')
            ->withCount('images')
            ->get();

        // Build online status map from Redis
        $onlineMap = [];
        foreach ($connections as $connection) {
            $onlineMap[$connection->id] = (bool) Redis::exists('user:online:' . $connection->id);
        }

        // Get unread counts per conversation partner
        $unreadCounts = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->select('sender_id', DB::raw('COUNT(*) as count'))
            ->groupBy('sender_id')
            ->pluck('count', 'sender_id')
            ->toArray();

        return view('chat.hub', compact('connections', 'onlineMap', 'unreadCounts', 'partnerId'));
    }

    /**
     * API: Get all conversation threads for the current user.
     */
    public function conversations(Request $request): JsonResponse
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

        return response()->json($conversations);
    }

    /**
     * API: Get history with a partner.
     */
    public function messages(User $partner): JsonResponse
    {
        $userId = Auth::id();

        if (!$this->isAcceptedConnection($userId, $partner->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        Message::where('sender_id', $partner->id)
            ->where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = Message::conversation($userId, $partner->id)
            ->with(['image'])
            ->orderBy('created_at', 'asc')
            ->take(50)
            ->get()
            ->map(function ($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            });

        return response()->json([
            'messages' => $messages,
            'partner'  => [
                'id'        => $partner->id,
                'name'      => $partner->name,
                'avatar'    => $partner->avatar,
                'is_online' => (bool) Redis::exists('user:online:' . $partner->id),
            ],
        ]);
    }

    /**
     * API: Send message (text or image).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'receiver_id' => 'required|uuid|exists:users,id',
            'body'        => 'nullable|string|max:2000',
            'image_id'    => 'nullable|uuid|exists:images,id',
        ]);

        if (!$request->body && !$request->image_id) {
            return response()->json(['error' => 'Message cannot be empty'], 422);
        }

        $userId = Auth::id();

        if (!$this->isAcceptedConnection($userId, $request->receiver_id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Optional: Verify image ownership if image_id is provided
        if ($request->image_id) {
            $image = Image::find($request->image_id);
            if ($image->user_id !== $userId) {
                return response()->json(['error' => 'You can only share your own images'], 403);
            }
        }

        $message = Message::create([
            'sender_id'   => $userId,
            'receiver_id' => $request->receiver_id,
            'image_id'    => $request->image_id,
            'body'        => $request->body,
        ]);

        // Trigger Notification
        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            $receiver->notify(new ChatMessageNotification(Auth::user(), $request->body ?? 'Shared an image'));
        }

        try {
            event(new MessageSent($message->load(['sender', 'image'])));
        } catch (\Throwable $e) {}

        return response()->json($this->formatMessage($message, $userId), 201);
    }

    /**
     * API: Poll.
     */
    public function poll(User $partner, Request $request): JsonResponse
    {
        $userId = Auth::id();
        $afterId = $request->query('after_id', 0);

        $newMessages = Message::conversation($userId, $partner->id)
            ->where('id', '>', $afterId)
            ->with(['image'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) use ($userId) {
                return $this->formatMessage($msg, $userId);
            });

        if ($newMessages->isNotEmpty()) {
            Message::where('sender_id', $partner->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return response()->json([
            'messages'  => $newMessages,
            'is_online' => (bool) Redis::exists('user:online:' . $partner->id),
        ]);
    }

    /**
     * API: Get my images for the media picker.
     */
    public function myImages(): JsonResponse
    {
        $images = Auth::user()->images()
            ->latest()
            ->paginate(12)
            ->through(fn($img) => [
                'id'    => $img->id,
                'url'   => $img->url,
                'thumb' => app(\App\Services\Core\AssetDeliveryService::class)->getUrl($img, 'thumbnail'),
                'title' => $img->title,
            ]);

        return response()->json($images);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Message::unreadFor(Auth::id())->count();
        return response()->json(['count' => $count]);
    }

    /**
     * API: Get my connections to share images.
     */
    public function connections(): JsonResponse
    {
        $connections = Auth::user()->acceptedConnections()
            ->with(['profile'])
            ->get()
            ->map(fn($conn) => [
                'id' => $conn->id,
                'name' => $conn->name,
                'avatar' => $conn->avatar,
            ]);

        return response()->json(['connections' => $connections]);
    }

    private function formatMessage(mixed $msg, string $userId): array
    {
        return [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'image_id'   => $msg->image_id,
            'album_id'   => $msg->album_id,
            'image_url'  => $msg->image_id ? $msg->image->url : null,
            'thumb_url'  => $msg->image_id ? app(\App\Services\Core\AssetDeliveryService::class)->getUrl($msg->image, 'thumbnail') : null,
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
