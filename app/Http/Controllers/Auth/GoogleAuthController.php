<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // Find user by OAuth google_id or by email
            $user = User::where('email', $googleUser->getEmail())
                ->orWhereHas('oauth', function ($q) use ($googleUser) {
                    $q->where('google_id', $googleUser->getId());
                })
                ->first();

            if ($user) {
                // Update existing user OAuth info
                $user->oauth()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'google_id' => $googleUser->getId(),
                        'provider_token' => $googleUser->token,
                    ]
                );
                
                // Update avatar if provided
                if ($googleUser->getAvatar()) {
                    $user->provider_avatar = $googleUser->getAvatar();
                    $user->save();
                }

            } else {
                // Create a completely new user
                // The 'static::created' hook in User model will handle Profile & Handle generation
                $user = User::create([
                    'email' => $googleUser->getEmail(),
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'name' => \App\Helpers\RestrictedNameHelper::getSafeFallbackName($googleUser->getName(), $googleUser->getEmail()),
                    'google_id' => $googleUser->getId(),
                    'provider_token' => $googleUser->token,
                    'provider_name' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'provider_avatar' => $googleUser->getAvatar(),
                    'is_verified' => true,
                ]);

                // Create default status to prevent null relation issues
                if (class_exists(\App\Models\UserStatus::class)) {
                    $user->userStatus()->firstOrCreate([], ['status' => 'active']);
                }
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard'));

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Google Auth Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
}
