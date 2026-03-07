<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('verify-reset-code', [NewPasswordController::class, 'createCode'])
        ->name('password.verify.code');

    Route::post('verify-reset-code', [NewPasswordController::class, 'verifyCode'])
        ->name('password.verify.code.post');

    Route::get('reset-password', [NewPasswordController::class, 'create'])
        ->name('password.reset');


    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::post('email/verification-notification', [\App\Http\Controllers\Auth\VerifyCodeController::class, 'resend'])
        ->name('verification.send');
});

Route::post('verify-code/resend', [\App\Http\Controllers\Auth\VerifyCodeController::class, 'resend'])
    ->name('verification.resend.guest');
