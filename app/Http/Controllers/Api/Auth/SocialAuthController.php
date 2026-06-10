<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SocialAuthController extends Controller
{
    public function redirectToProvider(Request $request, $provider)
    {
        try {
            // Save the origin in state so we know where to redirect back to dynamically
            $origin = $request->query('origin');
            $state = $origin ? $origin : 'prod';
            
            return Socialite::driver($provider)
                ->with(['prompt' => 'select_account', 'state' => $state])
                ->stateless()
                ->redirect();
        } catch (\Exception $e) {
            Log::error("Social Redirect Failed ({$provider}): " . $e->getMessage());
            return redirect()->route('login')->with('error', 'Invalid provider or configuration');
        }
    }

    /**
     * Obtain the user information from the provider.
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Unified check: provider_id OR email
            $user = User::where(function ($query) use ($socialUser, $provider) {
                $query->where('provider_name', $provider)
                    ->where('provider_id', $socialUser->getId());
            })->orWhere('email', $socialUser->getEmail())->first();

            $displayName = $socialUser->getName() ?? $socialUser->getNickname() ?? 'User';

            if (!$user) {
                // New User Creation: The User model's 'booted' event handles profile creation
                $user = User::create([
                    'name' => $displayName,
                    'email' => $socialUser->getEmail() ?? $socialUser->getId() . "@{$provider}.local",
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);

                try {
                    $user->sendWelcomeEmailIfNeeded();
                } catch (\Exception $e) {
                    Log::warning("Welcome Email failed for social user {$user->email}: " . $e->getMessage());
                }
            } else {
                // Update existing user with fresh social data
                $user->update([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                ]);

                // Ensure profile exists (for users created before the profile system or if deleted)
                if (!$user->profile) {
                    $user->profile()->create([
                        'name' => $displayName,
                        'username' => \App\Helpers\RestrictedNameHelper::generateUniqueHandle($displayName),
                        'username_last_changed_at' => null,
                    ]);
                } elseif (empty($user->profile->name)) {
                    // Sync name to profile if it was empty
                    $user->profile->update(['name' => $displayName]);
                }
            }

            $token = $user->createToken("{$provider}_login_token")->plainTextToken;
            Log::info("Social Login Successful for user: {$user->email} via {$provider}");

            $state = $request->query('state');
            
            // Check if state is a valid URL (the origin passed from the frontend)
            if ($state && filter_var($state, FILTER_VALIDATE_URL)) {
                $frontendBase = rtrim($state, '/');
            } else {
                // Fallback for older setups
                $frontendBase = config('app.frontend_url', 'https://www.opalshot.studio');
            }
                
            $frontendUrl = $frontendBase . '/auth/callback?token=' . urlencode($token);
            return redirect()->away($frontendUrl);

        } catch (\Exception $e) {
            Log::error("Social Login Callback Failed ({$provider}): " . $e->getMessage());
            
            $isLocal = $request->query('state') === 'local';
            $frontendBase = $isLocal 
                ? 'http://localhost:5173' 
                : config('app.frontend_url', 'https://www.opalshot.studio');
                
            $errorUrl = $frontendBase . '/login?error=auth_failed';
            return redirect()->away($errorUrl);
        }
    }
}
