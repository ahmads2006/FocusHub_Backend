<?php

namespace App\Notifications;

use App\Models\SharedLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SharedLinkLeakDetected extends Notification implements ShouldQueue
{
    use Queueable;

    protected SharedLink $link;
    protected array $metadata;

    /**
     * Create a new notification instance.
     */
    public function __construct(SharedLink $link, array $metadata)
    {
        $this->link = $link;
        $this->metadata = $metadata;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function merchantsMail(object $notifiable): MailMessage
    {
        $shareableName = $this->link->shareable?->title ?? 'العنصر';
        $targetLabel = $this->link->label ?? 'غير مسمى';

        return (new MailMessage)
            ->error()
            ->subject('⚠️ تنبيه أمني: اكتشاف محاولة تسريب رابط سري')
            ->greeting('مرحباً ' . $notifiable->name)
            ->line("لقد تم اكتشاف محاولة دخول غير مصرح بها للرابط السري المخصص لـ (**{$targetLabel}**).")
            ->line("العنصر المشارك: **{$shareableName}**")
            ->line("تنبيه: تم حظر الوصول لهذا الشخص الغريب لحماية خصوصيتك.")
            ->action('إدارة الروابط المشتركة', url('/admin/shared-links'))
            ->line('تفاصيل الجهاز المتسلل:')
            ->line('IP: ' . ($this->metadata['ip'] ?? 'Unknown'))
            ->line('الموقع التقريبي: ' . ($this->metadata['geo'] ?? 'Unknown'))
            ->line('شكراً لاستخدامك OpalShot!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'security_leak_detected',
            'link_id' => $this->link->id,
            'target_label' => $this->link->label,
            'shareable_title' => $this->link->shareable?->title,
            'metadata' => $this->metadata,
            'message' => "محاولة تسريب للرابط المخصص لـ ({$this->link->label}). تم حظر المتسلل بنجاح.",
        ];
    }
}
