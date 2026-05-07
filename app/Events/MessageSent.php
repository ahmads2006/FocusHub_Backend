<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
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
            new \Illuminate\Broadcasting\PrivateChannel('App.Models.User.' . $this->message->receiver_id),
            new \Illuminate\Broadcasting\PrivateChannel('App.Models.User.' . $this->message->sender_id),
        ];
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        // Ensure sender is loaded
        $sender = $this->message->sender;
        $senderName = $sender->name ?? 'User';
        $senderAvatar = $sender->avatar ?? null;

        return [
            'id'          => $this->message->id,
            'sender_id'   => $this->message->sender_id,
            'receiver_id' => $this->message->receiver_id,
            'body'        => $this->message->body,
            'created_at'  => is_string($this->message->created_at) ? $this->message->created_at : $this->message->created_at->toISOString(),
            'sender'      => [
                'id'     => $this->message->sender_id,
                'name'   => $senderName,
                'avatar' => $senderAvatar,
            ],
            'conversation_id' => $this->message->conversation_id ?? null,
        ];
    }
}
