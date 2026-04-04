<?php

namespace App\Notifications;

use App\Models\Image;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class ImageSocialNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $actor;
    protected $image;
    protected $type; // 'like', 'bookmark'

    public function __construct(User $actor, Image $image, string $type)
    {
        $this->actor = $actor;
        $this->image = $image;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }


    public function broadcastType()
    {
        return 'image.social';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => "image_{$this->type}",
            'data' => $this->toArray($notifiable)
        ]);
    }

    public function toArray($notifiable)
    {
        $actionTextAr = ($this->type === 'like' ? 'أعجب بـ' : 'قام بحفظ');
        $message = "قام {$this->actor->name} بـ {$actionTextAr} صورتك: {$this->image->title}";

        return [
            'type' => "image_{$this->type}",
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_avatar' => $this->actor->avatar,
            'image_id' => $this->image->id,
            'image_title' => $this->image->title,
            'message' => $message,
            
            'action_url' => route('images.gallery') . '#image-' . $this->image->id,
        ];
    }
}
