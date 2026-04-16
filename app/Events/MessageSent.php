<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message|\App\Models\Mongo\ChatMessage $message;

    public function __construct(Message|\App\Models\Mongo\ChatMessage $message)
    {
        $this->message = $message;
    }

    /**
     * The private channel the event broadcasts on.
     * Only the receiver can listen to this channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->message->receiver_id),
        ];
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        // For MongoMessage, sender is actually loaded manually or we send only sender_id and let auth/frontend resolve,
        // but here we just manually pass sender details if loaded.
        $senderName = $this->message->sender->name ?? 'User';
        $senderAvatar = $this->message->sender->avatar ?? null;

        return [
            'id'         => $this->message->id,
            'sender_id'  => $this->message->sender_id,
            'body'       => $this->message->body,
            'created_at' => is_string($this->message->created_at) ? $this->message->created_at : $this->message->created_at->toISOString(),
            'sender'     => [
                'name'   => $senderName,
                'avatar' => $senderAvatar,
            ],
        ];
    }
}
