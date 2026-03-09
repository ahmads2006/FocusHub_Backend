<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AlbumController;

// ────────────────────────────────────────────────
// Dashboard
// ────────────────────────────────────────────────
Route::get('/', [DashboardController::class, 'index'])->middleware(['auth', 'check.verified', 'check.banned'])->name('dashboard');

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
Route::middleware(['auth', 'check.verified', 'check.banned', 'check.timeout'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/security', [ProfileController::class, 'updateSecurity'])->name('profile.security.update');
    Route::put('/profile/photography', [ProfileController::class, 'updatePhotography'])->name('profile.photography.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/profile/info', [ProfileController::class, 'stayLoggedInfo'])->name('profile.info');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/timer', [TimerController::class, 'index'])->name('timer.index');
    Route::get('/activities', [ActivityController::class, 'index'])->middleware('permission:view-activity-logs')->name('activities.index');

    // إدارة الصور والألبومات (Consolidated)
    Route::get('/manage-images', [ImageController::class, 'manage'])->name('images.index');
    Route::post('/manage-images', [ImageController::class, 'store'])->name('images.store');
    Route::delete('/manage-images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
    Route::post('/manage-albums', [ImageController::class, 'storeAlbum'])->name('albums.store');

    Route::get('/gallery', [ImageController::class, 'gallery'])->name('gallery.index');

    // Laboratory (Feature Testing)
    Route::get('/lab', function() {
        return view('lab.dashboard');
    })->name('lab.index');
    Route::post('/lab/process', [\App\Http\Controllers\LabController::class, 'process'])->name('lab.process');

    // Album Collaboration & Management
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::post('/albums/{album}/collaborators', [AlbumController::class, 'addCollaborator'])->name('albums.collaborators.add');
    Route::delete('/albums/{album}/collaborators/{user}', [AlbumController::class, 'removeCollaborator'])->name('albums.collaborators.remove');
});

// ─── لوحة الإدارة (super-admin only) ─────────────────────────────
Route::middleware(['auth', 'check.verified', 'ProtectAdminPanel'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [AdminDashboardController::class, 'users'])->name('users.index');
    Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
    Route::post('/roles', [AdminRoleController::class, 'store'])->name('roles.store');
    Route::delete('/roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');
    Route::get('/activities', [AdminDashboardController::class, 'activities'])->name('activities.index');
    Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])->name('users.ban');
    Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])->name('users.unban');
    Route::post('/users/{user}/shadow', [AdminUserController::class, 'toggleShadow'])->name('users.shadow');
    Route::post('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');

    // ─── إدارة الصور (Admin Photo Management) ───────────────────────
    Route::get('/photos', [App\Http\Controllers\Admin\PhotoController::class, 'index'])->name('photos.index');
    Route::post('/photos/{image}/visibility', [App\Http\Controllers\Admin\PhotoController::class, 'toggleVisibility'])->name('photos.visibility');
    Route::delete('/photos/{image}', [App\Http\Controllers\Admin\PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('/photos/{image}/ban', [App\Http\Controllers\Admin\PhotoController::class, 'ban'])->name('photos.ban');
    Route::get('/banned-hashes', [App\Http\Controllers\Admin\PhotoController::class, 'bannedHashes'])->name('banned_hashes.index');
    Route::delete('/banned-hashes/{hash}', [App\Http\Controllers\Admin\PhotoController::class, 'unbanHash'])->name('banned_hashes.destroy');
});

// ────────────────────────────────────────────────
// Logout
// ────────────────────────────────────────────────
Route::get('/logout', function () {
    Auth::logout();
    return redirect()->route('login');
})->name('logout');

// ─── Shared Links (Public Access with Token) ─────────────────────
Route::middleware(\App\Http\Middleware\ValidateSharedLink::class)->group(function () {
    Route::get('/s/{token}', [App\Http\Controllers\SharedLinkController::class, 'show'])->name('shared.link.show');
    Route::post('/s/{token}/verify', [App\Http\Controllers\SharedLinkController::class, 'verifyPassword'])->name('shared.link.verify');
});

// Generate View Once Link (Authenticated)
Route::middleware(['auth', 'check.verified'])->post('/images/{image}/share-once', [App\Http\Controllers\SharedLinkController::class, 'generateShareOnceLink'])->name('images.share.once');

// Generate Custom Shared Link (Authenticated)
Route::middleware(['auth', 'check.verified'])->post('/share/generate', [App\Http\Controllers\SharedLinkController::class, 'generate'])->name('share.generate');

Route::middleware(['signed'])->get('/assets/original/{image}', [App\Http\Controllers\AssetAccessController::class, 'serveOriginal'])->name('assets.original');

require __DIR__.'/auth.php';
