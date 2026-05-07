<?php

namespace App\Events;

use App\Models\SupportMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportMessage $message;

    public function __construct(SupportMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Broadcast on the user's private support channel.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support.' . $this->message->conversation->user_id),
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
            'is_system'       => $this->message->is_system,
            'created_at'      => $this->message->created_at->toISOString(),
            'sender'          => [
                'id'     => $this->message->sender_id,
                'name'   => $this->message->is_system ? 'System' : ($this->message->sender->name ?? 'User'),
            ],
        ];
    }
}
