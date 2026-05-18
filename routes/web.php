<?php

use Illuminate\Support\Facades\Route;

// ────────────────────────────────────────────────
// Redirect Root to Frontend
// ────────────────────────────────────────────────
Route::get('/', function () {
    return redirect(config('app.frontend_url', 'https://www.opalshot.studio'));
});

// Redirect shared links to frontend with dynamic Open Graph tags
Route::get('/s/{token}', function ($token) {
    $frontendUrl = rtrim(config('app.frontend_url', 'https://www.opalshot.studio'), '/');
    $redirectUrl = $frontendUrl . '/s/' . $token;

    try {
        $tokenHash = hash('sha256', $token);
        $link = \App\Models\SharedLink::with('shareable')->where('token_hash', $tokenHash)->first();

        if ($link && $link->shareable) {
            $shareable = $link->shareable;
            $title = 'OpalShot';
            $description = 'شاهد هذا المحتوى المذهل والمشارك عبر OpalShot. View this amazing shared content on OpalShot.';
            $imageUrl = 'https://api.opalshot.studio/default-cover.jpg';

            if (class_basename($shareable) === 'Album') {
                $title = $shareable->title ?? 'ألبوم صور | Photo Album';
                $description = $shareable->description ?? "شاهد الألبوم المذهل. View the stunning album: {$title}.";
                $cover = $shareable->images()->first();
                if ($cover) {
                    $imageUrl = $cover->url_thumbnail;
                }
            } elseif (class_basename($shareable) === 'Image') {
                $title = $shareable->title ?? 'صورة مميزة';
                $imageUrl = $shareable->url_thumbnail;
            }

            return view('meta_proxy', compact('title', 'description', 'imageUrl', 'redirectUrl'));
        }
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Meta Proxy Error: ' . $e->getMessage());
    }

    return redirect($redirectUrl);
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

Route::get('/test-honeybadger', function () {
    throw new Exception('My first Honeybadger error!');
});

Route::fallback(function () {
    // Exclude API routes from this fallback, let Laravel API handler deal with 404s
    if (request()->is('api/*')) {
        return response()->json(['message' => 'Not Found.'], 404);
    }
    return redirect(config('app.frontend_url', 'https://www.opalshot.studio'));
});

