<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class SupportRequestNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $requester;

    public function __construct(User $requester)
    {
        $this->requester = $requester;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }



    public function broadcastType()
    {
        return 'support.request';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'support_request',
            'data' => $this->toArray($notifiable),
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'support_request',
            'requester_id' => $this->requester->id,
            'requester_name' => $this->requester->name,
            'requester_avatar' => $this->requester->avatar,
            'message' => "{$this->requester->name} يود التواصل مع الدعم الفني",
            'action_url' => route('chat.hub'),
        ];
    }
}
