<?php

namespace App\Mail;

use App\Models\ImageAppeal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppealApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $appeal;
    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct(ImageAppeal $appeal)
    {
        $this->appeal = $appeal;
        $this->url = route('images.index'); 
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'قبول طلب المراجعة - OpticVault',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.appeals.approved',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
