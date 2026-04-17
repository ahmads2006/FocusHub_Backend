<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ScannerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ╔══════════════════════════════════════════════════════════════════╗
// ║                    LEGACY API (Pre-V1)                          ║
// ╚══════════════════════════════════════════════════════════════════╝

Route::middleware(['auth:sanctum', 'check.banned', 'throttle:api'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('images', App\Http\Controllers\Api\ImageController::class)->names('api.images');
    Route::get('/user/drive-stats', [App\Http\Controllers\Api\StatsController::class, 'driveStats'])->name('api.user.drive-stats');
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

// ─── [LEGACY] Internal Scanner API (Python background scanner only) ─────
// Deactivated: Migrated to Cloud-native AI Moderation for v23.0+
// Route::prefix('internal/scanner')->group(function () {
//     Route::get('/images', [ScannerController::class, 'publicImages']);
//     Route::post('/report', [ScannerController::class, 'report']);
// });

// ─── Webhooks ──────────────────────────────────────────────
Route::post('/webhooks/imagekit', [\App\Http\Controllers\Api\ImageKitWebhookController::class, 'handle'])
    ->name('api.webhooks.imagekit');

// ─── Diagnostic API ──────────────────────────────────────────
Route::get('/health', [App\Http\Controllers\Api\SystemHealthController::class, 'index'])
    ->middleware(['auth:sanctum', 'role:super-admin']);

Route::get('/ping', function () {
    return response()->json([
        'status' => 'UP',
        'message' => 'OpalShot API is operational',
        'timestamp' => now()->toIso8601String()
    ]);
});

// ─── Instagram Authentication (Unified) ──────────────────────────
Route::prefix('auth/{provider}')->group(function () {
    Route::get('/redirect', [\App\Http\Controllers\Api\Auth\SocialAuthController::class, 'redirectToProvider']);
    Route::get('/callback', [\App\Http\Controllers\Api\Auth\SocialAuthController::class, 'handleProviderCallback']);
});


// ╔══════════════════════════════════════════════════════════════════╗
// ║                   API V1 — Full REST API                        ║
// ║          All routes return JSON via Sanctum Auth                 ║
// ╚══════════════════════════════════════════════════════════════════╝

Route::prefix('v1')->group(function () {

    // ══════════════════════════════════════════
    // 1. 🔐 AUTHENTICATION (Public)
    // ══════════════════════════════════════════
    Route::prefix('auth')->group(function () {
        Route::post('/register', [App\Http\Controllers\Api\V1\AuthController::class, 'register'])
            ->middleware('throttle:sensitive');

        Route::post('/login', [App\Http\Controllers\Api\V1\AuthController::class, 'login'])
            ->middleware('throttle:sensitive');

        Route::post('/forgot-password', [App\Http\Controllers\Api\V1\AuthController::class, 'forgotPassword'])
            ->middleware('throttle:sensitive');



        Route::post('/reset-password', [App\Http\Controllers\Api\V1\AuthController::class, 'resetPassword']);

        // Authenticated auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
            Route::post('/verify-code', [App\Http\Controllers\Api\V1\AuthController::class, 'verifyCode']);
            Route::post('/verify-code/resend', [App\Http\Controllers\Api\V1\AuthController::class, 'resendVerificationCode'])
                ->middleware('throttle:sensitive');
            Route::get('/me', [App\Http\Controllers\Api\V1\AuthController::class, 'me']);
        });
    });

    // ══════════════════════════════════════════
    // 2. 🔒 AUTHENTICATED ROUTES
    // ══════════════════════════════════════════
    Route::middleware(['auth:sanctum', 'check.banned', 'throttle:api'])->group(function () {

        // ─── 👤 Profile ──────────────────────────
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
            Route::patch('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
            Route::put('/security', [App\Http\Controllers\Api\V1\ProfileController::class, 'updateSecurity']);
            Route::put('/photography', [App\Http\Controllers\Api\V1\ProfileController::class, 'updatePhotography']);
            Route::post('/avatar', [App\Http\Controllers\Api\V1\ProfileController::class, 'updateAvatar']);
            Route::delete('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'destroy']);
            Route::get('/drive-stats', [App\Http\Controllers\Api\V1\ProfileController::class, 'driveStats']);
        });

        // ─── 🖼️ Images (Enhanced CRUD) ──────────
        Route::prefix('images')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\ImageController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\ImageController::class, 'store'])
                ->middleware('throttle:sensitive');
            Route::get('/{image}', [App\Http\Controllers\Api\ImageController::class, 'show']);
            Route::put('/{image}', [App\Http\Controllers\Api\ImageController::class, 'update']);
            Route::delete('/{image}', [App\Http\Controllers\Api\ImageController::class, 'destroy']);

            // SecureShield Protection
            Route::post('/{image}/protect', [App\Http\Controllers\Web\ImageController::class, 'protect']);
            Route::delete('/{image}/protect', [App\Http\Controllers\Web\ImageController::class, 'revert']);

            // Downloads
            Route::get('/{image}/download', [App\Http\Controllers\Web\DownloadController::class, 'download']);
            Route::get('/{image}/download-original', [App\Http\Controllers\Web\DownloadController::class, 'downloadOriginal'])
                ->middleware('signed');

            // Social Interactions
            Route::post('/{image}/like', [App\Http\Controllers\Api\V1\FeedController::class, 'like'])
                ->middleware('throttle:likes');
            Route::post('/{image}/bookmark', [App\Http\Controllers\Api\V1\FeedController::class, 'bookmark'])
                ->middleware('throttle:likes');
            Route::post('/{image}/view', [App\Http\Controllers\Api\V1\FeedController::class, 'trackView']);
            Route::post('/{image}/dwell', [App\Http\Controllers\Api\V1\FeedController::class, 'trackDwell']);
            Route::post('/{image}/not-interested', [App\Http\Controllers\Api\V1\FeedController::class, 'notInterested']);

            // Reporting
            Route::post('/{image}/report', [App\Http\Controllers\Api\V1\ReportController::class, 'store']);

            // Appeals
            Route::post('/{image}/appeal', [App\Http\Controllers\Api\V1\AppealController::class, 'store']);

            // Sharing
            Route::post('/{image}/share-once', [App\Http\Controllers\Api\V1\SharedLinkController::class, 'generateShareOnceLink']);
        });

        // ─── 📁 Albums ──────────────────────────
        Route::prefix('albums')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\AlbumController::class, 'index']);
            Route::post('/', [App\Http\Controllers\Api\V1\AlbumController::class, 'store']);
            Route::get('/{album}', [App\Http\Controllers\Api\V1\AlbumController::class, 'show']);
            Route::put('/{album}', [App\Http\Controllers\Api\V1\AlbumController::class, 'update']);
            Route::post('/{album}/request-delete-otp', [App\Http\Controllers\Api\V1\AlbumController::class, 'requestDeleteOTP']);
            Route::delete('/{album}', [App\Http\Controllers\Api\V1\AlbumController::class, 'destroy']);

            // Collaborators
            Route::post('/{album}/collaborators', [App\Http\Controllers\Api\V1\AlbumController::class, 'addCollaborator']);
            Route::put('/{album}/collaborators/{user}', [App\Http\Controllers\Api\V1\AlbumController::class, 'updateCollaboratorRole']);
            Route::delete('/{album}/collaborators/{user}', [App\Http\Controllers\Api\V1\AlbumController::class, 'removeCollaborator']);

            // Invitations
            Route::post('/{album}/invitation/accept', [App\Http\Controllers\Api\V1\AlbumController::class, 'acceptInvitation']);
            Route::post('/{album}/invitation/decline', [App\Http\Controllers\Api\V1\AlbumController::class, 'declineInvitation']);

            // Album Status (Moderation Polling)
            Route::get('/{album}/status', [App\Http\Controllers\Api\AlbumUploadController::class, 'getAlbumStatus']);
        });

        // ─── 🎨 Gallery ─────────────────────────
        Route::get('/gallery', [App\Http\Controllers\Api\V1\GalleryController::class, 'index']);

        // ─── 🛠️ Utilities & Helpers ─────────────────
        Route::prefix('utils')->group(function () {
            Route::get('/format-bytes', [App\Http\Controllers\Api\V1\UtilityController::class, 'formatBytes']);
            Route::get('/format-number', [App\Http\Controllers\Api\V1\UtilityController::class, 'formatNumber']);
            Route::get('/mask-email', [App\Http\Controllers\Api\V1\UtilityController::class, 'maskEmail']);
            Route::get('/image-orientation', [App\Http\Controllers\Api\V1\UtilityController::class, 'imageOrientation']);
            Route::get('/test-response', [App\Http\Controllers\Api\V1\UtilityController::class, 'testResponseHelper']);
        });

        // ─── 🎯 Feed (For You Algorithm) ────────
        Route::get('/feed/for-you', [App\Http\Controllers\Api\V1\FeedController::class, 'forYou']);

        // ─── 📦 Bulk Upload ─────────────────────
        Route::prefix('upload')->group(function () {
            Route::post('/album', [App\Http\Controllers\Api\AlbumUploadController::class, 'uploadAlbum'])
                ->middleware('throttle:batch-album');
            Route::post('/batch', [App\Http\Controllers\Api\AlbumUploadController::class, 'uploadBatch'])
                ->middleware('throttle:batch-album');
            Route::get('/progress/{jobId}', [App\Http\Controllers\Api\AlbumUploadController::class, 'getUploadProgress']);
        });

        // ─── 🔗 Shared Links ────────────────────
        Route::post('/share/generate', [App\Http\Controllers\Api\V1\SharedLinkController::class, 'generate']);

        // ─── 🤝 Connections ─────────────────────
        Route::prefix('connections')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\ConnectionController::class, 'index']);
            Route::post('/{user}/toggle', [App\Http\Controllers\Api\V1\ConnectionController::class, 'toggle']);
            Route::get('/{user}/status', [App\Http\Controllers\Api\V1\ConnectionController::class, 'status']);
        });

        // ─── 💬 Chat ────────────────────────────
        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [App\Http\Controllers\Api\V1\MongoChatController::class, 'conversations']);
            Route::get('/messages/{partner}', [App\Http\Controllers\Api\V1\MongoChatController::class, 'messages']);
            Route::post('/send', [App\Http\Controllers\Api\V1\MongoChatController::class, 'store']);
            Route::get('/poll/{partner}', [App\Http\Controllers\Api\V1\MongoChatController::class, 'poll']);
            Route::get('/unread-count', [App\Http\Controllers\Api\V1\MongoChatController::class, 'unreadCount']);
            Route::get('/my-images', [App\Http\Controllers\Api\V1\MongoChatController::class, 'myImages']);
            Route::get('/connections', [App\Http\Controllers\Api\V1\MongoChatController::class, 'connections']);

            // ─── 👥 Group Chat ──────────────────────
            Route::prefix('groups')->group(function () {
                Route::get('/', [App\Http\Controllers\Api\V1\GroupChatController::class, 'index']);
                Route::post('/', [App\Http\Controllers\Api\V1\GroupChatController::class, 'store']);
                Route::get('/{conversation}', [App\Http\Controllers\Api\V1\GroupChatController::class, 'show']);
                Route::post('/{conversation}/send', [App\Http\Controllers\Api\V1\GroupChatController::class, 'sendMessage']);
                Route::get('/{conversation}/poll', [App\Http\Controllers\Api\V1\GroupChatController::class, 'poll']);
                Route::post('/{conversation}/participants', [App\Http\Controllers\Api\V1\GroupChatController::class, 'addParticipant']);
                Route::delete('/{conversation}/participants/{user}', [App\Http\Controllers\Api\V1\GroupChatController::class, 'removeParticipant']);
                Route::patch('/{conversation}/rename', [App\Http\Controllers\Api\V1\GroupChatController::class, 'rename']);
            });
        });

        // ─── 🎧 Support ────────────────────────
        Route::prefix('support')->group(function () {
            // User endpoints
            Route::get('/conversation', [App\Http\Controllers\Api\V1\SupportController::class, 'getOrCreateConversation']);
            Route::post('/send', [App\Http\Controllers\Api\V1\SupportController::class, 'sendMessage']);
            Route::get('/poll/{conversation}', [App\Http\Controllers\Api\V1\SupportController::class, 'pollMessages']);

            // Admin endpoints
            Route::middleware('permission:access-admin-panel')->prefix('admin')->group(function () {
                Route::get('/pending', [App\Http\Controllers\Api\V1\SupportController::class, 'pendingConversations']);
                Route::get('/count', [App\Http\Controllers\Api\V1\SupportController::class, 'pendingCount']);
                Route::post('/claim/{conversation}', [App\Http\Controllers\Api\V1\SupportController::class, 'claimConversation']);
                Route::get('/messages/{conversation}', [App\Http\Controllers\Api\V1\SupportController::class, 'adminMessages']);
                Route::post('/send', [App\Http\Controllers\Api\V1\SupportController::class, 'adminSend']);
                Route::get('/poll/{conversation}', [App\Http\Controllers\Api\V1\SupportController::class, 'adminPoll']);
                Route::post('/close/{conversation}', [App\Http\Controllers\Api\V1\SupportController::class, 'closeConversation']);
            });
        });

        // ─── 🔔 Notifications ───────────────────
        Route::prefix('notifications')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
            Route::post('/{id}/read', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [App\Http\Controllers\Api\V1\NotificationController::class, 'destroy']);
        });

        // ─── 📋 Appeals (User History) ──────────
        Route::get('/appeals', [App\Http\Controllers\Api\V1\AppealController::class, 'index']);

        // ─── 👁️ Public Photographer Profile ─────
        Route::get('/photographers/{user}', [App\Http\Controllers\Api\V1\ProfileController::class, 'showPhotographer']);
    });

    // ══════════════════════════════════════════
    // 3. 🛡️ ADMIN API (V1)
    // ══════════════════════════════════════════
    Route::prefix('admin')->middleware(['auth:sanctum', 'check.banned', 'role:super-admin'])->group(function () {

        // Moderation
        Route::get('/moderation', [App\Http\Controllers\Api\V1\ModerationController::class, 'index']);
        Route::post('/moderation/{image}/approve', [App\Http\Controllers\Api\V1\ModerationController::class, 'approve']);
        Route::post('/moderation/{image}/reject', [App\Http\Controllers\Api\V1\ModerationController::class, 'reject']);
        Route::post('/reports/{report}/resolve/{action}', [App\Http\Controllers\Api\V1\ModerationController::class, 'resolveReport']);

        // Appeals Management
        Route::get('/appeals', [App\Http\Controllers\Api\V1\AppealController::class, 'adminIndex']);
        Route::post('/appeals/{appeal}/approve', [App\Http\Controllers\Api\V1\AppealController::class, 'approve']);
        Route::post('/appeals/{appeal}/reject', [App\Http\Controllers\Api\V1\AppealController::class, 'reject']);

        // Users (reuse existing Legacy AdminController)
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/{user}/ban', [AdminController::class, 'banUser']);
        Route::post('/users/{user}/unban', [AdminController::class, 'unbanUser']);
        Route::post('/users/{user}/shadow-toggle', [AdminController::class, 'toggleShadowHidden']);
        Route::get('/users/{user}/activity', [AdminController::class, 'userActivity']);
        Route::get('/activities', [AdminController::class, 'allActivities']);
        Route::put('/users/{user}/role', [AdminController::class, 'upgradeUserRole']);

        // Content Management
        Route::put('/albums/{album}/privacy', [AdminController::class, 'setAlbumPrivacy']);
        Route::delete('/albums/{album}', [AdminController::class, 'deleteAlbum']);
        Route::put('/images/{image}/privacy', [AdminController::class, 'setImagePrivacy']);
        Route::delete('/images/{image}', [AdminController::class, 'deleteImage']);
    });

    // ══════════════════════════════════════════
    // 4. 🔗 SHARED LINKS (Public with Token)
    // ══════════════════════════════════════════
    Route::middleware(\App\Http\Middleware\ValidateSharedLink::class)->group(function () {
        Route::get('/share/{token}', [App\Http\Controllers\Api\V1\SharedLinkController::class, 'show']);
        Route::post('/share/{token}/verify', [App\Http\Controllers\Api\V1\SharedLinkController::class, 'verifyPassword']);
        Route::get('/share/{token}/download', [App\Http\Controllers\Api\V1\SharedLinkController::class, 'downloadAlbum']);
    });
});

