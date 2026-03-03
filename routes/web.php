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



Route::get('/verify-code', function () {
    return view('auth.verify-code');
})->name('verify.code');

Route::post('/verify-code', function (Illuminate\Http\Request $request) {
    $request->validate(['code' => 'required|numeric']);

    if ($request->code == session('verification_code')) {
        $userId = session('temp_user_id');
        if ($userId && $user = \App\Models\User::find($userId)) {
            Auth::login($user);
            session()->forget(['verification_code', 'temp_user_id']);
            return redirect()->route('dashboard');
        }
    }

    return back()->withErrors(['code' => 'الرمز غير صحيح أو انتهت صلاحية الجلسة.']);
})->name('verify.code.post');

Route::get('/test-courier', function () {
    try {
        // يمكنك مراجعة الكود المرسل في ملف storage/logs/laravel.log
        // تأكد من وجود المستخدم وتخزين معرفه في الجلسة ليعمل منطق التحقق
        $user = \App\Models\User::where('email', 'hrobahmad9@gmail.com')->firstOrFail();
        
        \Illuminate\Support\Facades\Log::info("Attempting to send Courier email to: " . $user->email);

        $courier = new Client(env('COURIER_AUTH_TOKEN'));
        $verificationCode = (string) rand(100000, 999999);

        session([
            'verification_code' => $verificationCode,
            'temp_user_id' => $user->id,
        ]);

        $response = $courier->send->message([
            'to' => ['email' => $user->email],
            'template' => 'DT14H2T7T44X4DGFSWX8J1DC',
            'data' => [
                'verification_code' => $verificationCode,
            ],
        ]);

        \Illuminate\Support\Facades\Log::info("Courier Response: " . json_encode($response));

        return redirect()->route('verify.code');

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("Courier Catch Error: " . $e->getMessage());
        return "خطأ: " . $e->getMessage();
    }
});

