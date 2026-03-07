<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        if (!session('reset_password_email')) {
            return redirect()->route('password.request');
        }
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Display the code verification view.
     */
    public function createCode(): View
    {
        if (!session('reset_password_email')) {
            return redirect()->route('password.request');
        }
        return view('auth.verify-reset-code');
    }

    /**
     * Verify the 6-digit code.
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|numeric']);

        $email = session('reset_password_email');
        
        $resetData = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $request->code)
            ->first();

        if ($resetData) {
            // التحقق من أن الكود لم تنتهِ صلاحيته (اختياري، مثلاً بعد ساعة)
            return redirect()->route('password.reset', ['token' => 'verified_by_code']); 
        }

        return back()->withErrors(['code' => __('الرمز غير صحيح.')]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = session('reset_password_email');
        if (!$email) {
            return redirect()->route('password.request')->withErrors(['email' => __('Session expired. Please request a new code.')]);
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));

            // حذف الرمز من قاعدة البيانات بعد نجاح العملية
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            session()->forget(['reset_password_email']);

            return redirect()->route('login')->with('status', __('passwords.reset'));
        }

        return back()->withErrors(['email' => __('passwords.user')]);
    }
}
