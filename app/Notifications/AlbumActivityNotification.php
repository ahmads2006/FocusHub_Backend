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

class AlbumActivityNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $actor;
    protected $album;
    protected $action; // 'uploaded', 'deleted'
    protected $count;

    public function __construct(User $actor, Album $album, string $action, int $count = 1)
    {
        $this->actor = $actor;
        $this->album = $album;
        $this->action = $action;
        $this->count = $count;
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
        return [new PrivateChannel('App.Models.User.' . $this->actor->id)];
    }

    public function broadcastType()
    {
        return 'album.activity';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => "album_activity_{$this->action}",
            'data' => $this->toArray($notifiable)
        ]);
    }

    public function toArray($notifiable)
    {
        $actionText = ($this->action === 'uploaded' ? 'بإضافة' : 'بحذف');
        $itemText = ($this->count > 1 ? "{$this->count} صور" : "صورة");
        $message = "قام {$this->actor->name} {$actionText} {$itemText} في الألبوم المشترك: {$this->album->title}";

        return [
            'type' => "album_activity_{$this->action}",
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'album_id' => $this->album->id,
            'album_title' => $this->album->title,
            'message' => $message,
            'action_url' => route('images.index', ['album' => $this->album->id]),
        ];
    }
}
