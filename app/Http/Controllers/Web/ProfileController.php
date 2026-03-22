<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update the user's security settings.
     */
    public function updateSecurity(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->stay_logged_in = $request->has('stay_logged_in');
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'security-updated');
    }

    /**
     * Update photography preferences.
     */
    public function updatePhotography(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->dynamic_watermark = $request->has('dynamic_watermark');
        $user->auto_orient_default = $request->has('auto_orient_default');
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'photography-updated');
    }

    /**
     * Update user avatar.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|max:5120', // 5MB max for avatar
        ]);

        $user = $request->user();

        // Use ImageService to process and store the avatar
        $service = app(\App\Services\Core\ImageService::class);
        $path = $service->generateAvatar($request->file('profile_picture'));

        // Delete old avatar if it exists and is not a default UI avatar
        if ($user->profile_picture && !str_contains($user->profile_picture, 'ui-avatars.com')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
        }

        $user->update(['profile_picture' => $path]);

        return Redirect::route('profile.edit')->with('status', 'avatar-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Provide stay logged info for frontend info button.
     */
    public function stayLoggedInfo(Request $request)
    {
        $message = 'إبقاء الجلسة نشطة دائماً يعني أن حسابك سيبقى مسجلاً دون تسجيل خروج تلقائي. يمكنك إلغاء ذلك في أي وقت بتعطيل هذا الخيار.';
        return response()->json(['message' => $message]);
    }

    /**
     * Display the public profile gallery of a selected user.
     */
    public function show(\App\Models\User $user): View
    {
        // Fetch stats from Redis if exist, else default to 0
        $redisPrefix = "user:{$user->id}:stats";
        $totalLikes = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:likes") ?: 0;
        $totalPhotos = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:photos") ?: 0;
        $totalConnections = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:connections") ?: 0;

        // Fetch images where is_public = true (privacy = public) and moderation_status = 'approved'
        $images = $user->images()
            ->where('privacy', 'public')
            ->whereHas('moderation', function ($q) {
                $q->where('status', \App\Models\Image::STATUS_APPROVED);
            })
            ->with(['likes', 'labels'])
            ->latest()
            ->paginate(24);

        return view('profile.show', [
            'profileUser' => $user,
            'images' => $images,
            'stats' => [
                'likes' => $totalLikes,
                'photos' => $totalPhotos,
                'connections' => $totalConnections,
            ]
        ]);
    }
}
