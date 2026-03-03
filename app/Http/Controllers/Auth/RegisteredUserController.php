<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

       

        // 1. توليد رمز تحقق عشوائي مكون من 6 أرقام
        $verificationCode = (string) rand(100000, 999999);

        // 2. تخزين الرمز ومعرف المستخدم في الجلسة لمطابقته لاحقاً
        session([
            'verification_code' => $verificationCode,
            'temp_user_id' => $user->id
        ]);

        // 3. إرسال الرمز عبر Resend باستخدام Mail facade
        \Illuminate\Support\Facades\Log::info("Verification code for {$user->email}: {$verificationCode}");
        try {
            Mail::raw("مرحباً {$user->name}، رمز التحقق الخاص بك هو: {$verificationCode}", function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('رمز التحقق الخاص بك');
            });
        } catch (\Exception $e) {
            // في حالة فشل الإرسال برمجياً، يمكن تسجيل الخطأ في الـ Log
            \Illuminate\Support\Facades\Log::error("Resend Mail Error: " . $e->getMessage());
        }

        // 4. التوجه لصفحة إدخال الرمز بدلاً من لوحة التحكم
        return redirect()->route('verify.code');
    }
}