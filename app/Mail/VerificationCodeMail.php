<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    // 1. نقوم بتعريف متغير لاستقبال الرمز
    public $code;
    public $userName;

    /**
     * نمرر الرمز (وإسم المستخدم إذا أردت) عند إنشاء الكائن
     */
    public function __construct($code, $userName)
    {
        $this->code = $code;
        $this->userName = $userName;
    }

    /**
     * عنوان الرسالة كما سيظهر في صندوق الوارد
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز التحقق الخاص بك - OpticVault',
        );
    }

    /**
     * ربط الملف بتصميم الـ Blade
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification', // تأكد من إنشاء هذا الملف في resources/views/emails/verification.blade.php
        );
    }

    public function attachments(): array
    {
        return [];
    }
}