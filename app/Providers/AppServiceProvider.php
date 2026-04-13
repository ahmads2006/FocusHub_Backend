<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (class_exists(\Sentry\ClientBuilder::class) && ! app()->runningInConsole()) {
            $this->app->extend(\Sentry\ClientBuilder::class, function (\Sentry\ClientBuilder $builder) {
                $builder->setTransport(new \App\Sentry\LaravelQueueTransport());
                return $builder;
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Socialite Providers Registration ──────────────────────────
        $socialiteListeners = [
            \SocialiteProviders\Instagram\InstagramExtendSocialite::class . '@handle',
            \SocialiteProviders\Adobe\AdobeExtendSocialite::class . '@handle',
        ];

        foreach ($socialiteListeners as $listener) {
            \Illuminate\Support\Facades\Event::listen(
                \SocialiteProviders\Manager\SocialiteWasCalled::class,
                $listener
            );
        }

        // ── Album Observer (sync group chat name with album title) ──
        \App\Models\Album::observe(\App\Observers\AlbumObserver::class);


        // ────────────────────────────────────────────────
        // Tunnel Configuration (ngrok)
        // ────────────────────────────────────────────────
        if (!app()->runningInConsole()) {
            $host = request()->getHost();
            if (str_contains($host, 'ngrok-free')) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
                \Illuminate\Support\Facades\URL::forceRootUrl('https://' . $host);
                
                // Dynamically fix Google Redirect URI for the tunnel
                config(['services.google.redirect' => 'https://' . $host . '/auth/google/callback']);
                
                // Ensure session cookies work over HTTPS tunnels
                config(['session.secure' => true]);
                config(['session.same_site' => 'none']);
            }
        }

        // Define the gate for the super admin
        Gate::before(function (User $user) {
            if ($user->role === 'super_admin') {
                return true;
            }
            return null;
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // Redis-backed rate limiter for preventing like spam (10 per minute per user)
        RateLimiter::for('likes', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // ── Multi-Tiered Rate Limiting ──────────────────────────

        // Sensitive Tier: Single uploads & resend-code (strict)
        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'محاولات كثيرة جداً. الذكاء الاصطناعي يعالج صورك السابقة، يرجى الانتظار لحظة.',
                    ], 429);
                });
        });

        // Batch Album Tier: Parallel UI-driven uploads (flexible)
        RateLimiter::for('batch-album', function (Request $request) {
            return Limit::perMinute(100)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'محاولات كثيرة جداً. الذكاء الاصطناعي يعالج صورك السابقة، يرجى الانتظار لحظة.',
                    ], 429);
                });
        });

        // Web Tier: General navigation (standard)
        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many requests. Please slow down.',
                    ], 429);
                });
        });

        // API Tier: Mobile/external API access
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(80)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many API requests. Please wait a moment.',
                    ], 429);
                });
        });
    }

}
