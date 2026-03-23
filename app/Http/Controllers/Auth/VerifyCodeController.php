<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyCodeController extends Controller
{
    /**
     * عرض صفحة إدخال رمز التحقق.
     */
    public function show()
    {
        $user = Auth::user() ?? User::find(session('temp_user_id'));

        if ($user && !$user->is_verified) {
            // Auto-send code if not already sent or if expired (simimplified: always send if visiting)
            $user->sendVerificationEmail();
        }

        return view('auth.verify-code');
    }

    /**
     * التحقق من الرمز المُدخل وتفعيل الحساب.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|digits:6',
        ]);

        $userId = session('temp_user_id') ?? Auth::id();

        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['code' => 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول من جديد.']);
        }

        if (!$user) {
            return redirect()->route('register')
                ->withErrors(['code' => 'المستخدم غير موجود.']);
        }

        // ── Check Expiration (3 Minutes) ──
        $verification = $user->verification;
        if ($verification && $verification->updated_at->addMinutes(3)->isPast()) {
            return back()->withErrors(['code' => 'انتهت صلاحية هذا الكود (3 دقائق). يرجى طلب كود جديد.']);
        }

        if ($request->code !== $user->verification_code) {
            return back()->withErrors(['code' => 'الرمز غير صحيح. يرجى المحاولة مرة أخرى.']);
        }

        // Activate account in DB
        $user->update([
            'is_verified' => true,
            'verification_code' => null,
        ]);

        $response = redirect()->route('dashboard');

        // Handle Trusted Device
        if ($request->boolean('trust_device')) {
            $rawToken = \Illuminate\Support\Str::random(64);
            $hashedToken = hash('sha256', $rawToken);
            
            $user->verification()->update([
                'device_token' => $hashedToken,
                'device_trusted_until' => now()->addDays(30),
                'last_login_ip' => $request->ip(),
            ]);

            // Set secure cookie
            $response->withCookie(cookie()->make(
                'opticvault_trusted_device',
                $rawToken,
                60 * 24 * 30, // 30 days
                null,
                null,
                true, // Secure
                true, // HttpOnly
                false,
                'Lax'
            ));
        }

        // Login if needed (guest flow)
        if (session()->has('temp_user_id')) {
            Auth::login($user);
            session()->forget('temp_user_id');
        }

        // Mark session as 2fa verified
        session(['2fa_verified' => true]);

        return $response;
    }

    /**
     * إعادة إرسال رمز التحقق.
     */
    public function resend(Request $request)
    {
        $user = Auth::user() ?? User::find(session('temp_user_id'));

        if (!$user) {
            return redirect()->route('login');
        }

        $user->sendVerificationEmail();

        return back()->with('status', 'verification-link-sent');
    }
}
