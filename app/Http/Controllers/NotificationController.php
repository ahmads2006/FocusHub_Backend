<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Facades\Mail;

class NotificationController extends Controller
{
    /**
     * إرسال رمز تحقق تجريبي - يستخدم للاختبار اليدوي فقط.
     * في الإنتاج، يتم الإرسال تلقائياً من RegisteredUserController عبر User::sendVerificationEmail()
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $code = (string) rand(100000, 999999);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name));

        return view('notifications.index');
    }
}
