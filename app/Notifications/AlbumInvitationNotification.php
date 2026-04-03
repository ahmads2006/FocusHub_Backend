<?php

namespace App\Notifications;

use App\Models\Album;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class AlbumInvitationNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $album;
    protected $inviter;

    public function __construct(Album $album, User $inviter)
    {
        $this->album = $album;
        $this->inviter = $inviter;
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
        return [new PrivateChannel('App.Models.User.' . $this->inviter->id)];
    }

    /**
     * The event name for broadcasting.
     */
    public function broadcastType()
    {
        return 'album.invitation';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'album_invitation',
            'data' => $this->toArray($notifiable)
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'album_invitation',
            'album_id' => $this->album->id,
            'album_title' => $this->album->title,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'message' => "قام {$this->inviter->name} بدعوتك للانضمام إلى الألبوم التعاوني: {$this->album->title}",
            'action_url' => route('chat.hub', ['partner' => $this->inviter->id]),
        ];
    }
}
