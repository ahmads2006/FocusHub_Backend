<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Find the user
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withInput($request->only('email'))
                        ->withErrors(['email' => __('passwords.user')]);
        }

        // Generate a 6-digit code
        $resetCode = (string) rand(100000, 999999);

        // تخزين الرمز في قاعدة البيانات (في جدول password_reset_tokens) لترتبط الميزة بالداتابيز
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => $resetCode,
                'created_at' => now(),
            ]
        );

        // Store email in session to know who is being reset
        session(['reset_password_email' => $user->email]);

        Log::info("Generated Password Reset Code for {$user->email}: {$resetCode}");

        // إرسال الرمز عبر HTML email باستخدام VerificationCodeMail
        try {
            Mail::to($user->email)->send(new VerificationCodeMail($resetCode, $user->name));
            Log::info("Password Reset Code sent to: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Password Reset Email Error: " . $e->getMessage());
            return back()->withInput($request->only('email'))
                        ->withErrors(['email' => 'حدث خطأ أثناء إرسال البريد الإلكتروني. الرجاء المحاولة مرة أخرى.']);
        }

        return redirect()->route('password.verify.code');
    }
}
