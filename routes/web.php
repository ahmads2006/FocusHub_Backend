<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;

// ────────────────────────────────────────────────
// Dashboard
// ────────────────────────────────────────────────
Route::get('/', [DashboardController::class, 'index'])->middleware(['auth', 'check.verified'])->name('dashboard');

// ────────────────────────────────────────────────
// Guest routes
// ────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
});

// ────────────────────────────────────────────────
// Email verification routes
// ────────────────────────────────────────────────
Route::get('/verify-code', [VerifyCodeController::class, 'show'])->name('verify.code');
Route::post('/verify-code', [VerifyCodeController::class, 'verify'])->name('verify.code.post');

// ────────────────────────────────────────────────
// Authenticated + verified routes
// ────────────────────────────────────────────────
Route::middleware(['auth', 'check.verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/timer', [TimerController::class, 'index'])->name('timer.index');
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');

    // إدارة الصور والألبومات (Consolidated)
    Route::get('/manage-images', [ImageController::class, 'manage'])->name('images.test.index');
    Route::post('/manage-images', [ImageController::class, 'store'])->name('images.test.store');
    Route::delete('/manage-images/{image}', [ImageController::class, 'destroy'])->name('images.test.destroy');
    Route::post('/manage-albums', [ImageController::class, 'storeAlbum'])->name('albums.test.store');

    Route::get('/gallery', [ImageController::class, 'gallery'])->name('gallery.index');
});

// ────────────────────────────────────────────────
// Logout
// ────────────────────────────────────────────────
Route::get('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout');

require __DIR__.'/auth.php';
