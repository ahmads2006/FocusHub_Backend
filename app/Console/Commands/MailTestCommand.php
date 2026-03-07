<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use Exception;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email}';
    protected $description = 'Send a test verification email and report status';

    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Attempting to send a test email to: {$email}");

        try {
            Mail::to($email)->send(new VerificationCodeMail('123456', 'Tester'));
            $this->info("✅ Mail sent successfully according to Laravel.");
            $this->warn("Note: This only means the vendor (Resend) accepted the request. Check your inbox/spam.");
        } catch (Exception $e) {
            $this->error("❌ Failed to send mail!");
            $this->error("Error: " . $e->getMessage());
            
            if (str_contains($e->getMessage(), '401')) {
                $this->warn("Tip: This looks like an API Key issue (Unauthorized).");
            } elseif (str_contains($e->getMessage(), '403')) {
                $this->warn("Tip: This might be a domain verification or permission issue.");
            }
        }
    }
}
