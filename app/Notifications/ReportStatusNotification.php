<?php

namespace App\Notifications;

use App\Models\Image;
use App\Models\ImageReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;

class ReportStatusNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    protected $report;
    protected $action;
    protected $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(ImageReport $report, string $action, string $message)
    {
        $this->report = $report;
        $this->action = $action; // 'resolved' or 'dismissed'
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the type of the notification being broadcast.
     */
    public function broadcastType()
    {
        return 'report.status';
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'report_status',
            'data' => $this->toArray($notifiable)
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        $isAr = app()->getLocale() === 'ar';
        $title = $this->action === 'resolved' 
            ? ($isAr ? 'تم معالجة البلاغ' : 'Report Resolved')
            : ($isAr ? 'تم رفض البلاغ' : 'Report Dismissed');

        return [
            'type' => 'report_status',
            'title' => $title,
            'report_id' => $this->report->id,
            'image_id' => $this->report->image_id,
            'action' => $this->action,
            'message' => $this->message,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
