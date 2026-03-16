<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

        // Register Observers
        \App\Models\AlbumSettings::observe(\App\Observers\AlbumSettingsObserver::class);

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
    }

}
