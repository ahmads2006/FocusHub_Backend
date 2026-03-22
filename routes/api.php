<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ScannerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.banned'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('images', App\Http\Controllers\Api\ImageController::class)->names('api.images');
});

// ─── Admin API (super-admin only) ──────────────────────────────
Route::prefix('admin')->middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users/{user}/ban', [AdminController::class, 'banUser']);
    Route::post('/users/{user}/unban', [AdminController::class, 'unbanUser']);
    Route::post('/users/{user}/shadow-toggle', [AdminController::class, 'toggleShadowHidden']);
    Route::get('/users/{user}/activity', [AdminController::class, 'userActivity']);
    Route::get('/activities', [AdminController::class, 'allActivities']);
    Route::put('/users/{user}/role', [AdminController::class, 'upgradeUserRole']);

    Route::put('/albums/{album}/privacy', [AdminController::class, 'setAlbumPrivacy']);
    Route::delete('/albums/{album}', [AdminController::class, 'deleteAlbum']);
    Route::put('/images/{image}/privacy', [AdminController::class, 'setImagePrivacy']);
    Route::delete('/images/{image}', [AdminController::class, 'deleteImage']);
});

// ─── Internal Scanner API (Python background scanner only) ─────
Route::prefix('internal/scanner')->group(function () {
    Route::get('/images', [ScannerController::class, 'publicImages']);
    Route::post('/report', [ScannerController::class, 'report']);
});

// ─── Webhooks ──────────────────────────────────────────────
Route::post('/webhooks/imagekit', [\App\Http\Controllers\Api\ImageKitWebhookController::class, 'handle'])
    ->name('api.webhooks.imagekit');

// ─── Diagnostic API ──────────────────────────────────────────
Route::get('/health', [App\Http\Controllers\Api\SystemHealthController::class, 'index']);
