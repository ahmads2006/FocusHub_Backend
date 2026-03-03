<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimerController;
use Courier\Client;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
 
Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/timer', [TimerController::class, 'index'])->name('timer.index');
});

Route::get('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout');

require __DIR__.'/auth.php';



    


// مسار تجريبي لإرسال رمز التحقق عبر Courier
Route::get('/test-courier', function () {
    try {
        // 1. الاتصال بـ Courier باستخدام المفتاح الموجود في .env
       $courier = new Client(env('COURIER_AUTH_TOKEN'));

        // 2. توليد رمز تحقق عشوائي
        $verificationCode = (string) rand(100000, 999999);

        // 3. إرسال الإشعار مع البيانات
        $result = $courier->send->message([
            'to' => [
                'email' => 'hrobahmad9@gmail.com',
            ],
            'template' => 'DT14H2T7T44X4DGFSWX8J1DC', // الـ ID الخاص بك من الصورة
            'data' => [
                'recipientName' => 'Ahmad',
                'project_name' => 'FocusHub',
                'verification_code' => $verificationCode, // الرمز الذي نريد التحقق منه
            ],
        ]);

        return "تم إرسال الرمز ($verificationCode) إلى لوحة تحكم Courier بنجاح! <br> رقم الطلب: " . $result->requestID;

    } catch (\Exception $e) {
        return "حدث خطأ أثناء الاتصال بـ Courier: " . $e->getMessage();
    }
});

