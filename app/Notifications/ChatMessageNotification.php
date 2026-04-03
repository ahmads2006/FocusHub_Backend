<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class ChatMessageNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $sender;
    protected $body;

    public function __construct(User $sender, string $body)
    {
        $this->sender = $sender;
        $this->body = \Illuminate\Support\Str::limit($body, 100);
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * The channels the notification should broadcast on.
     */
    public function broadcastOn()
    {
        return [new PrivateChannel('App.Models.User.' . $this->sender->id)];
    }

    public function broadcastType()
    {
        return 'chat.message';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'new_chat_message',
            'data' => $this->toArray($notifiable)
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'new_chat_message',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'sender_avatar' => $this->sender->avatar,
            'message' => "لديك رسالة جديدة من {$this->sender->name}: \"{$this->body}\"",
            'action_url' => route('chat.hub', ['partner' => $this->sender->id]),
        ];
    }
}
