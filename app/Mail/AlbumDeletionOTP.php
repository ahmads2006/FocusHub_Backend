<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlbumDeletionOTP extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $albumTitle;

    /**
     * Create a new message instance.
     */
    public function __construct($code, $albumTitle)
    {
        $this->code = $code;
        $this->albumTitle = $albumTitle;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز التحقق لحذف الألبوم - OpticVault',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.albums.deletion-otp',
            with: [
                'code' => $this->code,
                'albumTitle' => $this->albumTitle,
            ],
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
