<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Courier\Client;
use App\Models\User;

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

        // Store the code and email in the session
        session([
            'reset_password_code' => $resetCode,
            'reset_password_email' => $user->email,
        ]);

        Log::info("Generated Password Reset Code for {$user->email}: {$resetCode}");

        // Send the code via Laravel Mail (Resend)
        try {
            \Illuminate\Support\Facades\Mail::raw("مرحباً {$user->name}، رمز استعادة كلمة المرور الخاص بك هو: {$resetCode}", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('رمز استعادة كلمة المرور');
            });
            Log::info("Password Reset Code sent to: {$user->email}");
        } catch (\Exception $e) {
            Log::error("Password Reset Email Error: " . $e->getMessage());
            return back()->withInput($request->only('email'))
                        ->withErrors(['email' => 'حدث خطأ أثناء إرسال البريد الإلكتروني. الرجاء المحاولة مرة أخرى.']);
        }

        return redirect()->route('password.verify.code');
    }
}
