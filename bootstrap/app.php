<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\Authenticate;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            abort(response()->json(['message' => 'Unauthenticated.'], 401));
        });

         $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\TrackOnlineStatus::class,
        ]);
        $middleware->trustProxies(at: [
            '127.0.0.1',
            '10.0.0.0/8',       // DigitalOcean internal network
            '10.19.0.0/16',     // DigitalOcean VPC
            '172.16.0.0/12',    // Docker internal network
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'check.verified' => \App\Http\Middleware\CheckVerified::class,
            'check.banned' => \App\Http\Middleware\EnsureUserNotBanned::class,
            'ProtectAdminPanel' => \App\Http\Middleware\ProtectAdminPanel::class,
            'CheckSuperAdmin' => \App\Http\Middleware\CheckSuperAdmin::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'api/v1/*',
            'api/v1/auth/forgot-password',
            'api/v1/auth/reset-password',
            'api/v1/auth/login',
            'api/v1/auth/register',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception) {
            if (app()->bound('sentry') && app()->environment('production')) {
                app('sentry')->captureException($exception);
            }
            if (app()->bound('honeybadger')) {
                app('honeybadger')->notify($exception, app('request'));
            }
        });
        Integration::handles($exceptions);
    })->create();
