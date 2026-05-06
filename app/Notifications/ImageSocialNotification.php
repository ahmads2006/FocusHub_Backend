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
        $isAr = app()->getLocale() === 'ar';
        $actionTextAr = ($this->type === 'like' ? 'أعجب بـ' : 'قام بحفظ');
        $actionTextEn = ($this->type === 'like' ? 'liked' : 'bookmarked');
        
        $title = $this->type === 'like' ? 'إعجاب جديد' : 'حفظ جديد';
        $titleEn = $this->type === 'like' ? 'New Like' : 'New Bookmark';
        
        $message = $isAr 
            ? "قام {$this->actor->name} بـ {$actionTextAr} صورتك: {$this->image->title}"
            : "{$this->actor->name} {$actionTextEn} your photo: {$this->image->title}";

        return [
            'type' => "image_{$this->type}",
            'title' => $isAr ? $title : $titleEn,
            'message' => $message,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'actor_avatar' => $this->actor->avatar,
            'image_id' => $this->image->id,
            'image_title' => $this->image->title,
            'action_url' => route('images.gallery') . '#image-' . $this->image->id,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
