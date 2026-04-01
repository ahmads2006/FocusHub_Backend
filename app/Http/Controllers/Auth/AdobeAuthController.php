<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use App\Helpers\RestrictedNameHelper;

class AdobeAuthController extends Controller
{
    /**
     * Redirect the user to the Adobe authentication page.
     */
    public function redirectToAdobe()
    {
        return Socialite::driver('adobe')->redirect();
    }

    /**
     * Obtain the user information from Adobe.
     */
    public function handleAdobeCallback()
    {
        try {
            $adobeUser = Socialite::driver('adobe')->user();

            // Find user by OAuth adobe_id (stored in user_oauth) or by email
            $user = User::where('email', $adobeUser->getEmail())
                ->orWhereHas('oauth', function ($q) use ($adobeUser) {
                    $q->where('adobe_id', $adobeUser->getId());
                })
                ->first();

            if ($user) {
                // Update existing user OAuth info
                $user->oauth()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'adobe_id' => $adobeUser->getId(),
                        'provider_token' => $adobeUser->token,
                    ]
                );
                
                // Update avatar if provided
                if ($adobeUser->getAvatar()) {
                    $user->provider_avatar = $adobeUser->getAvatar();
                    $user->save();
                }

            } else {
                // Create a completely new user
                $displayName = RestrictedNameHelper::getSafeFallbackName($adobeUser->getName(), $adobeUser->getEmail());
                
                $user = User::create([
                    'email' => $adobeUser->getEmail() ?: $adobeUser->getId() . '@adobe.local',
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                    'name' => $displayName,
                    'adobe_id' => $adobeUser->getId(),
                    'provider_token' => $adobeUser->token,
                    'provider_name' => 'adobe',
                    'provider_id' => $adobeUser->getId(),
                    'provider_avatar' => $adobeUser->getAvatar(),
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
            \Illuminate\Support\Facades\Log::error('Adobe Auth Error: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
}
