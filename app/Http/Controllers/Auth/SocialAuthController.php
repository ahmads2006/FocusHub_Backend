<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Helpers\RestrictedNameHelper;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirectToProvider($provider)
    {
        try {
            return Socialite::driver($provider)->redirect();
        } catch (Exception $e) {
            Log::error("Social Web Redirect Failed ({$provider}): " . $e->getMessage());
            return redirect()->route('login')->with('error', 'Authentication failed.');
        }
    }

    /**
     * Obtain the user information from the provider.
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();

            // Unified check: provider_id OR email
            $user = User::where(function ($query) use ($socialUser, $provider) {
                $query->where('provider_name', $provider)
                      ->where('provider_id', $socialUser->getId());
            })->orWhere('email', $socialUser->getEmail())->first();

            if (!$user) {
                $displayName = RestrictedNameHelper::getSafeFallbackName(
                    $socialUser->getName() ?? $socialUser->getNickname(), 
                    $socialUser->getEmail()
                );

                $user = User::create([
                    'name' => $displayName,
                    'email' => $socialUser->getEmail() ?? $socialUser->getId() . "@{$provider}.local",
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);

                // Ensure verification profile/settings are handled by boot or manually
                if ($user->verification) {
                    $user->verification->update(['is_verified' => true]);
                }
            } else {
                // Update existing user with social info
                $user->update([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                ]);
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard'));

        } catch (Exception $e) {
            Log::error("Social Web Login Callback Failed ({$provider}): " . $e->getMessage());
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
}
