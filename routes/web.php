<?php

use Illuminate\Support\Facades\Route;

// ────────────────────────────────────────────────
// Redirect Root to Frontend
// ────────────────────────────────────────────────
Route::get('/', function () {
    return redirect(config('app.frontend_url', 'https://www.opalshot.studio'));
});




// ────────────────────────────────────────────────
// Signed Assets Serving (Files, No HTML)
// ────────────────────────────────────────────────
Route::middleware(['signed'])->get('/assets/original/{image}', [\App\Http\Controllers\Web\AssetAccessController::class, 'serveOriginal'])->name('assets.original');
Route::middleware(['signed'])->get('/assets/preview/{image}', [\App\Http\Controllers\Web\AssetAccessController::class, 'servePreview'])->name('assets.preview');

// ────────────────────────────────────────────────
// Fallback for all other stray Web Routes
// ────────────────────────────────────────────────
if (app()->environment('local', 'development')) {
    Route::get('/preview/mail/{locale?}', function ($locale = 'ar') {
        app()->setLocale($locale);
        return new \App\Mail\VerificationCodeMail('123456', 'Ahmad Test');
    });

    Route::get('/preview/mail/welcome/{locale?}', function ($locale = 'ar') {
        app()->setLocale($locale);
        return new \App\Mail\WelcomeMail('Ahmad User');
    });

    Route::get('/preview/mail/register-verify/{locale?}', function ($locale = 'ar') {
        app()->setLocale($locale);
        return new \App\Mail\RegisterVerificationMail('888999', 'Sara New');
    });
}

Route::fallback(function () {
    // Exclude API routes from this fallback, let Laravel API handler deal with 404s
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Not Found.'], 404);
    }
    return redirect(config('app.frontend_url', 'https://www.opalshot.studio'));
});

