<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Pre-built payload to broadcast.
     * We do NOT use SerializesModels because the ChatMessage is a MongoDB model
     * and lazy-loading its cross-database relations during broadcast fails
     * with "Call to a member function prepare() on null".
     */
    public array $payload;
    private string $receiverId;
    private string $senderId;

    public function __construct(Message|\App\Models\Mongo\ChatMessage $message, ?array $senderData = null)
    {
        // Resolve sender data NOW while DB connections are available
        if ($senderData) {
            $senderName = $senderData['name'] ?? 'User';
            $senderAvatar = $senderData['avatar'] ?? null;
        } else {
            try {
                $sender = $message->sender;
                $senderName = $sender?->name ?? 'User';
                $senderAvatar = $sender?->avatar ?? null;
            } catch (\Throwable $e) {
                $senderName = 'User';
                $senderAvatar = null;
            }
        }

        $this->receiverId = (string) $message->receiver_id;
        $this->senderId = (string) $message->sender_id;

        $this->payload = [
            'id'          => (string) $message->id,
            'sender_id'   => $this->senderId,
            'receiver_id' => $this->receiverId,
            'body'        => $message->body,
            'created_at'  => is_string($message->created_at) ? $message->created_at : $message->created_at->toISOString(),
            'sender'      => [
                'id'     => $this->senderId,
                'name'   => $senderName,
                'avatar' => $senderAvatar,
            ],
            'conversation_id' => $message->conversation_id ?? null,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->receiverId),
            new PrivateChannel('chat.' . $this->senderId),
        ];
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
