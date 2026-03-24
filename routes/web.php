<?php

use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyCodeController;
use App\Http\Controllers\Web\ImageController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\TimerController;
use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\AlbumController;

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

    // Google OAuth Routes
    Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'handleGoogleCallback']);
});

// ────────────────────────────────────────────────
// Public Profile Gallery
// ────────────────────────────────────────────────
Route::get('/photographer/{user}', [ProfileController::class, 'show'])->name('profile.show');

// ────────────────────────────────────────────────
// Email verification routes
// ──────────────────────────────────────────────── 
Route::get('/verify-code', [VerifyCodeController::class, 'show'])->name('verify.code');
Route::post('/verify-code', [VerifyCodeController::class, 'verify'])->name('verify.code.post');

// ────────────────────────────────────────────────
// Authenticated + verified routes
// ────────────────────────────────────────────────
Route::middleware(['auth', 'check.verified', 'check.banned', 'check.timeout', 'throttle:web'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/security', [ProfileController::class, 'updateSecurity'])->name('profile.security.update');
    Route::put('/profile/photography', [ProfileController::class, 'updatePhotography'])->name('profile.photography.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/profile/info', [ProfileController::class, 'stayLoggedInfo'])->name('profile.info');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/activities', [ActivityController::class, 'index'])->middleware('permission:view-activity-logs')->name('activities.index');

    // إدارة الصور والألبومات (Consolidated)
    Route::get('/manage-my-vault', [ImageController::class, 'manage'])->name('images.index');
    Route::post('/manage-my-vault', [ImageController::class, 'store'])->middleware('throttle:sensitive')->name('images.store');
    Route::put('/manage-my-vault/{image}', [ImageController::class, 'update'])->name('images.update');
    Route::delete('/manage-my-vault/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
    Route::post('/manage-albums', [ImageController::class, 'storeAlbum'])->name('albums.store');
    Route::post('/images/{image}/protect', [ImageController::class, 'protect'])->name('images.protect');
    
    // Download Flow
    Route::get('/download/{image}', [DownloadController::class, 'download'])->name('images.download');
    Route::get('/images/{image}/download-original', [DownloadController::class, 'downloadOriginal'])
         ->name('images.download.original')
         ->middleware('signed');
    Route::delete('/images/{image}/protection', [ImageController::class, 'revert'])->name('images.protection.revert');

    // Discovery Algorithm & Recommendations
    Route::get('/for-you', [\App\Http\Controllers\Web\FeedController::class, 'index'])->name('feed.index');
    Route::get('/feed/for-you', [\App\Http\Controllers\Web\FeedController::class, 'forYou'])->name('feed.api.for_you');
    Route::post('/images/{image}/like', [\App\Http\Controllers\Web\FeedController::class, 'like'])->middleware('throttle:likes')->name('images.like');
    Route::post('/images/{image}/view', [\App\Http\Controllers\Web\FeedController::class, 'trackView'])->name('images.view');
    Route::post('/images/{image}/not-interested', [\App\Http\Controllers\Web\FeedController::class, 'notInterested'])->name('images.not_interested');


    // Bulk Upload System
    Route::get('/bulk-upload', [\App\Http\Controllers\Api\AlbumUploadController::class, 'index'])->name('images.bulk');
    Route::post('/api/upload/album', [\App\Http\Controllers\Api\AlbumUploadController::class, 'uploadAlbum'])->middleware('throttle:batch-album')->name('api.upload.album');
    Route::post('/api/upload/batch', [\App\Http\Controllers\Api\AlbumUploadController::class, 'uploadBatch'])->middleware('throttle:batch-album')->name('api.upload.batch');
    Route::get('/api/upload/progress/{jobId}', [\App\Http\Controllers\Api\AlbumUploadController::class, 'getUploadProgress'])->name('api.upload.progress');
    Route::get('/albums/{album}/status', [\App\Http\Controllers\Api\AlbumUploadController::class, 'getAlbumStatus'])->name('albums.status');

    Route::get('/gallery', [ImageController::class, 'gallery'])->name('images.gallery');
    

    // Laboratory (Feature Testing)
    Route::get('/lab', function() {
        return view('lab.dashboard');
    })->name('lab.index');
    Route::post('/lab/process', [\App\Http\Controllers\Web\LabController::class, 'process'])->name('lab.process');

    // Album Collaboration & Management
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::put('/albums/{album}', [AlbumController::class, 'update'])->name('albums.update');
    Route::post('/albums/{album}/request-delete-otp', [AlbumController::class, 'requestDeleteOTP'])->name('albums.request_delete_otp');
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])->name('albums.destroy');
    Route::post('/albums/{album}/collaborators', [AlbumController::class, 'addCollaborator'])->name('albums.collaborators.add');
    Route::delete('/albums/{album}/collaborators/{user}', [AlbumController::class, 'removeCollaborator'])->name('albums.collaborators.remove');
    // Album Invitation Accept/Decline
    Route::post('/albums/{album}/invitation/accept', [AlbumController::class, 'acceptInvitation'])->name('albums.invitation.accept');
    Route::post('/albums/{album}/invitation/decline', [AlbumController::class, 'declineInvitation'])->name('albums.invitation.decline');
    
    // Image Reporting & Appeals
    Route::post('/images/{image}/report', [App\Http\Controllers\Web\ReportController::class, 'image'])->name('images.report');
    
    // Appeals Routes
    Route::get('/appeals', [App\Http\Controllers\Web\AppealController::class, 'index'])->name('appeals.history');
    Route::get('/images/{image}/appeal', [App\Http\Controllers\Web\AppealController::class, 'create'])->name('images.appeal.create');
    Route::post('/images/{image}/appeal', [App\Http\Controllers\Web\AppealController::class, 'store'])->name('images.appeal');
    // Connection & Following
    Route::post('/connect/{user}', [\App\Http\Controllers\Web\ConnectionController::class, 'toggle'])->name('connect.toggle');
    Route::get('/connect/{user}/status', [\App\Http\Controllers\Web\ConnectionController::class, 'status'])->name('connect.status');
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

    // ─── إدارة الصور (Admin Photo Management & Moderation) ──────────────────
    Route::get('/moderation', [App\Http\Controllers\Web\Admin\ModerationController::class, 'index'])->name('moderation.index');
    Route::post('/photos/{image}/approve', [App\Http\Controllers\Web\Admin\ModerationController::class, 'approve'])->name('photos.approve');
    Route::post('/photos/{image}/reject', [App\Http\Controllers\Web\Admin\ModerationController::class, 'reject'])->name('photos.reject');
    Route::post('/users/{user}/ban-system', [App\Http\Controllers\Web\Admin\ModerationController::class, 'banUser'])->name('users.ban_system');
    Route::post('/reports/{report}/resolve/{action}', [App\Http\Controllers\Web\Admin\ModerationController::class, 'resolveReport'])->name('reports.resolve');
    
    // Appeals Management
    Route::get('/appeals', [App\Http\Controllers\Web\Admin\AppealController::class, 'index'])->name('appeals.index');
    Route::post('/appeals/{appeal}/approve', [App\Http\Controllers\Web\Admin\AppealController::class, 'approve'])->name('appeals.approve');
    Route::post('/appeals/{appeal}/reject', [App\Http\Controllers\Web\Admin\AppealController::class, 'reject'])->name('appeals.reject');

    Route::get('/photos', [App\Http\Controllers\Web\Admin\PhotoController::class, 'index'])->name('photos.index');
    Route::post('/photos/{image}/visibility', [App\Http\Controllers\Web\Admin\PhotoController::class, 'toggleVisibility'])->name('photos.visibility');
    Route::delete('/photos/{image}', [App\Http\Controllers\Web\Admin\PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::post('/photos/{image}/ban', [App\Http\Controllers\Web\Admin\PhotoController::class, 'ban'])->name('photos.ban');

    Route::get('/banned-hashes', [App\Http\Controllers\Web\Admin\PhotoController::class, 'bannedHashes'])->name('banned_hashes.index');
    Route::delete('/banned-hashes/{hash}', [App\Http\Controllers\Web\Admin\PhotoController::class, 'unbanHash'])->name('banned_hashes.destroy');
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
    Route::get('/s/{token}', [App\Http\Controllers\Web\SharedLinkController::class, 'show'])->name('shared_link.show');
    Route::get('/s/{token}/download-all', [App\Http\Controllers\Web\SharedLinkController::class, 'downloadAlbum'])->name('shared_link.download_album');
    Route::post('/s/{token}/verify', [App\Http\Controllers\Web\SharedLinkController::class, 'verifyPassword'])->name('shared_link.verify');
});

// Generate View Once Link (Authenticated)
Route::middleware(['auth', 'check.verified'])->post('/images/{image}/share-once', [App\Http\Controllers\Web\SharedLinkController::class, 'generateShareOnceLink'])->name('images.share.once');

// Generate Custom Shared Link (Authenticated)
Route::middleware(['auth', 'check.verified'])->post('/share/generate', [App\Http\Controllers\Web\SharedLinkController::class, 'generate'])->name('share.generate');

Route::middleware(['signed'])->get('/assets/original/{image}', [App\Http\Controllers\Web\AssetAccessController::class, 'serveOriginal'])->name('assets.original');
Route::middleware(['signed'])->get('/assets/preview/{image}', [App\Http\Controllers\Web\AssetAccessController::class, 'servePreview'])->name('assets.preview');

require __DIR__.'/auth.php';
Route::get('/debug/health', function() {
    return view('debug.health');
});
