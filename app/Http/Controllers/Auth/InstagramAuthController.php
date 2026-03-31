<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstagramAuthController extends Controller
{
    /**
     * Redirect the user to the Instagram authentication page.
     */
    public function redirectToInstagram()
    {
        return Socialite::driver('instagram')->redirect();
    }

    /**
     * Obtain the user information from Instagram.
     */
    public function handleInstagramCallback()
    {
        try {
            $instagramUser = Socialite::driver('instagram')->user();

            // Find user by OAuth instagram_id or by email
            $user = User::where('instagram_id', $instagramUser->getId())
                ->orWhere('email', $instagramUser->getEmail() ?? $instagramUser->getId() . '@instagram.local')
                ->first();

            if ($user) {
                // Update existing user with Instagram info
                $user->update([
                    'instagram_id' => $instagramUser->getId(),
                ]);
            } else {
                // Create a completely new user
                $user = User::create([
                    'name' => $instagramUser->getName() ?? $instagramUser->getNickname() ?? 'Instagram User',
                    'email' => $instagramUser->getEmail() ?? $instagramUser->getId() . '@instagram.local',
                    'instagram_id' => $instagramUser->getId(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);

                // Ensure relations are created
                if ($user->verification) {
                    $user->verification->update(['is_verified' => true]);
                }
            }

            Auth::login($user);

            return redirect()->intended(route('dashboard'));

        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Instagram Web Auth Error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
}
