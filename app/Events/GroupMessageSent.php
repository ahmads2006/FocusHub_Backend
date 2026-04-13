<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Broadcast on the group conversation's presence channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('group-chat.' . $this->message->conversation_id),
        ];
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'body'            => $this->message->body,
            'image_id'        => $this->message->image_id,
            'created_at'      => $this->message->created_at->toISOString(),
            'sender'          => [
                'id'     => $this->message->sender->id,
                'name'   => $this->message->sender->name,
                'avatar' => $this->message->sender->avatar,
            ],
        ];
    }
}
