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
    /**
     * Redirect the user to the provider authentication page.
     */
    public function redirectToProvider($provider)
    {
        try {
            return Socialite::driver($provider)->stateless()->redirect();
        } catch (\Exception $e) {
            Log::error("Social Redirect Failed ({$provider}): " . $e->getMessage());
            return redirect()->route('login')->with('error', 'Invalid provider or configuration');
        }
    }

    /**
     * Obtain the user information from the provider.
     */
    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();

            // Unified check: provider_id OR email
            $user = User::where(function ($query) use ($socialUser, $provider) {
                $query->where('provider_name', $provider)
                    ->where('provider_id', $socialUser->getId());
            })->orWhere('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail() ?? $socialUser->getId() . "@{$provider}.local",
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            } else {
                // Update existing user with new social info
                $user->update([
                    'provider_name' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'provider_avatar' => $socialUser->getAvatar(),
                ]);
            }

            $token = $user->createToken("{$provider}_login_token")->plainTextToken;

            // Redirect back to frontend with token
            $frontendUrl = env('FRONTEND_URL', 'https://opalshot.studio') . '/auth/callback?token=' . $token;
            return redirect()->away($frontendUrl);

        } catch (\Exception $e) {
            Log::error("Social Login Callback Failed ({$provider}): " . $e->getMessage());
            $errorUrl = env('FRONTEND_URL', 'https://opalshot.studio') . '/login?error=auth_failed';
            return redirect()->away($errorUrl);
        }
    }
}
