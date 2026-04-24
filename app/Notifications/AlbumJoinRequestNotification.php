<?php

namespace App\Notifications;

use App\Models\Album;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AlbumJoinRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $album;
    public $requester;
    public $requestedRole;

    /**
     * Create a new notification instance.
     */
    public function __construct(Album $album, User $requester, string $requestedRole)
    {
        $this->album = $album;
        $this->requester = $requester;
        $this->requestedRole = $requestedRole;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'album_id'       => $this->album->id,
            'album_title'    => $this->album->title,
            'requester_id'   => $this->requester->id,
            'requester_name' => $this->requester->name,
            'role'           => $this->requestedRole,
            'message'        => "طلب {$this->requester->name} الانضمام إلى ألبومك '{$this->album->title}' كـ ({$this->requestedRole}).",
            'type'           => 'album_join_request',
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
