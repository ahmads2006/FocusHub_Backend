<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Image;

class HighRiskImageUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    public $image;

    /**
     * Create a new notification instance.
     */
    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'High Risk image detected and quarantined.',
            'image_id' => $this->image->id,
            'reason' => $this->image->sensitivity_reason,
        ];
    }
}
