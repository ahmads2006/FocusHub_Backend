<?php

namespace App\Notifications;

use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Broadcasting\PrivateChannel;

class ImageStatusNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $image;
    protected $status;
    protected $message;

    public function __construct(Image $image, string $status, string $message)
    {
        $this->image = $image;
        $this->status = $status;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }



    public function broadcastType()
    {
        return 'image.status';
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'image_status',
            'data' => $this->toArray($notifiable)
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'type' => 'image_status',
            'image_id' => $this->image->id,
            'status' => $this->status, // 'success', 'failed', 'rejected'
            'message' => $this->message,
            'image_title' => $this->image->title,
            'action_url' => route('images.gallery') . '#image-' . $this->image->id,
        ];
    }
}
