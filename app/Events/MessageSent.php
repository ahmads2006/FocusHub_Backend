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

    public Message $message;

    public function __construct(Message $message)
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
        return [
            'id'         => $this->message->id,
            'sender_id'  => $this->message->sender_id,
            'body'       => $this->message->body,
            'created_at' => $this->message->created_at->toISOString(),
            'sender'     => [
                'name'   => $this->message->sender->name,
                'avatar' => $this->message->sender->avatar,
            ],
        ];
    }
}
