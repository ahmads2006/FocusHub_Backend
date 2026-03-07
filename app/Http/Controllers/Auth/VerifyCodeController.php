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

        $userId = session('temp_user_id') ?? Auth::id(); // Support both flows

        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['code' => 'انتهت صلاحية الجلسة. يرجى تسجيل الدخول من جديد.']);
        }

        $user = User::query()->find($userId);

        if (!$user) {
            return redirect()->route('register')
                ->withErrors(['code' => 'المستخدم غير موجود.']);
        }

        // مطابقة الرمز مع ما في قاعدة البيانات
        if ($request->code !== $user->verification_code) {
            return back()->withErrors(['code' => 'الرمز غير صحيح. يرجى المحاولة مرة أخرى.']);
        }

        // تفعيل الحساب في قاعدة البيانات
        $user->update([
            'is_verified' => true,
            'verification_code' => null, // مسح الرمز بعد الاستخدام
        ]);

        // تسجيل الدخول ومسح الجلسة المؤقتة
        if (session()->has('temp_user_id')) {
            Auth::login($user);
            session()->forget('temp_user_id');
        }

        return redirect()->route('dashboard');
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
