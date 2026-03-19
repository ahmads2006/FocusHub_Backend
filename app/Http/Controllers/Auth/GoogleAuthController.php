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
            $user = User::where('email', $googleUser->email)
                ->orWhereHas('oauth', function ($q) use ($googleUser) {
                    $q->where('google_id', $googleUser->id);
                })
                ->first();

            if ($user) {
                // Update existing user with Google info
                if (!$user->oauth) {
                    $user->oauth()->create([
                        'google_id' => $googleUser->id,
                        'provider_token' => $googleUser->token,
                    ]);
                } else {
                    $user->oauth()->update([
                        'google_id' => $googleUser->id,
                        'provider_token' => $googleUser->token,
                    ]);
                }

                // Update Profile
                if ($user->profile) {
                    $user->profile()->update([
                        'avatar' => $googleUser->avatar
                    ]);
                } else {
                    $user->profile()->create([
                        'name' => $googleUser->name,
                        'avatar' => $googleUser->avatar
                    ]);
                }

            } else {
                // Create a completely new user
                $user = User::create([
                    'email' => $googleUser->email,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)), // Placeholder password
                ]);

                // Manually fulfill verification status since it's Google
                $user->verification()->create([
                    'is_verified' => true,
                    'verified_at' => now(),
                ]);

                // Create profile
                $user->profile()->create([
                    'name' => $googleUser->name,
                    'avatar' => $googleUser->avatar,
                ]);

                // Create OAuth connection
                $user->oauth()->create([
                    'google_id' => $googleUser->id,
                    'provider_token' => $googleUser->token,
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
