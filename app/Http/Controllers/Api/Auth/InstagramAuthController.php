<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstagramAuthController extends Controller
{
    /**
     * Redirect the user to the Instagram authentication page.
     */
    public function redirectToInstagram()
    {
        $url = Socialite::driver('instagram')->stateless()->redirect()->getTargetUrl();
        
        return response()->json([
            'status' => 'success',
            'redirect_url' => $url
        ]);
    }

    /**
     * Obtain the user information from Instagram.
     */
    public function handleInstagramCallback(Request $request)
    {
        try {
            $instagramUser = Socialite::driver('instagram')->stateless()->user();

            $user = User::where('instagram_id', $instagramUser->getId())
                        ->orWhere('email', $instagramUser->getEmail() ?? $instagramUser->getId() . '@instagram.local')
                        ->first();

            if (!$user) {
                $user = User::create([
                    'name' => $instagramUser->getName() ?? $instagramUser->getNickname() ?? 'Instagram User',
                    'email' => $instagramUser->getEmail() ?? $instagramUser->getId() . '@instagram.local',
                    'instagram_id' => $instagramUser->getId(),
                    'password' => Hash::make(Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            }

            $token = $user->createToken('instagram_login_token')->plainTextToken;

            // Redirect back to frontend
            $frontendUrl = env('FRONTEND_URL', 'https://opticvault.me') . '/auth/callback?token=' . $token;
            return redirect()->away($frontendUrl);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Instagram Social Login Failed: " . $e->getMessage());
            $errorUrl = env('FRONTEND_URL', 'https://opticvault.me') . '/login?error=instagram_auth_failed';
            return redirect()->away($errorUrl);
        }
    }
}
