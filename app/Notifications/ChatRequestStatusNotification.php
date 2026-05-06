<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatRequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $sender;
    protected $status; // 'accepted' or 'declined'

    /**
     * Create a new notification instance.
     */
    public function __construct($sender, $status)
    {
        $this->sender = $sender;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $message = $this->status === 'accepted'
            ? "لقد قبل {$this->sender->name} طلب المراسلة الخاص بك. يمكنك الآن بدء المحادثة."
            : "نعتذر، {$this->sender->name} غير متاح للمراسلة في الوقت الحالي. شكراً لتفهمك.";

        if ($notifiable->preferred_language === 'en') {
            $message = $this->status === 'accepted'
                ? "{$this->sender->name} accepted your message request. You can now start chatting."
                : "Sorry, {$this->sender->name} is not available for messaging at the moment. Thanks for understanding.";
        }

        return [
            'type'        => 'chat_request_status',
            'sender_id'   => $this->sender->id,
            'sender_name' => $this->sender->name,
            'status'      => $this->status,
            'message'     => $message,
            'action_url'  => $this->status === 'accepted' ? '/chat' : null,
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => $this->toArray($notifiable),
        ]);
    }
}
