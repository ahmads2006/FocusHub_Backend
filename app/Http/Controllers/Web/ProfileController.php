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
        $user = $request->user();
        $profile = $user->profile;

        // Check if username is being changed
        if ($request->has('username') && $request->input('username') !== $profile->username) {
            // Check 30-day limit
            $lastChanged = $profile->username_last_changed_at;
            if ($lastChanged && $lastChanged->diffInDays(now()) < 30) {
                $daysRemaining = 30 - $lastChanged->diffInDays(now());
                return back()->withErrors(['username' => "لا يمكنك تغيير اسم المستخدم إلا مرة واحدة كل 30 يومًا. يتبقى $daysRemaining أيام."]);
            }
            $profile->username_last_changed_at = now();
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $profile->save(); // Force save profile for the timestamp change

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
        
        $validated = $request->validate([
            'dynamic_watermark' => ['boolean'],
            'auto_orient_default' => ['boolean'],
            'is_public_profile' => ['boolean'],
            'watermark_mode' => ['nullable', 'string', 'in:text,logo'],
            'watermark_text' => ['nullable', 'string', 'max:50'],
            'watermark_logo' => ['nullable', 'image', 'max:1024'],
            'watermark_text_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'watermark_neon_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'watermark_opacity' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $user->dynamic_watermark = $request->has('dynamic_watermark');
        $user->auto_orient_default = $request->has('auto_orient_default');
        $user->is_public_profile = $request->has('is_public_profile');

        // Single watermark mode (text XOR logo)
        if ($request->filled('watermark_mode')) {
            $user->watermark_mode = $validated['watermark_mode'];
        }
        
        if ($request->filled('watermark_text')) $user->watermark_text = $validated['watermark_text'];
        if ($request->filled('watermark_text_color')) $user->watermark_text_color = $validated['watermark_text_color'];
        if ($request->filled('watermark_neon_color')) $user->watermark_neon_color = $validated['watermark_neon_color'];
        if ($request->has('watermark_opacity')) $user->watermark_opacity = $validated['watermark_opacity'];

        // Handle Logo Upload
        if ($request->hasFile('watermark_logo')) {
            // Delete old logo
            if ($user->watermark_logo) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->watermark_logo);
            }
            // Store new logo
            $path = $request->file('watermark_logo')->store('watermarks', 'public');
            $user->watermark_logo = $path;
        }

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
    public function show(Request $request, \App\Models\User $user): View
    {
        $isOwner = Auth::id() === $user->id;
        $isPublic = (bool) $user->is_public_profile;
        $isFollowing = false;

        if (Auth::check() && !$isOwner) {
            $isFollowing = \App\Models\Connection::where('user_id', Auth::id())
                ->where('connected_user_id', $user->id)
                ->where('status', 'accepted')
                ->exists();
        }

        // Fetch stats from Redis if exist, else default to 0
        $redisPrefix = "user:{$user->id}:stats";
        $totalLikes = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:likes") ?: 0;
        $totalPhotos = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:photos") ?: 0;
        $totalConnections = (int) \Illuminate\Support\Facades\Redis::get("{$redisPrefix}:connections") ?: 0;

        $activeTab = $request->get('tab', 'public');
        if (!$isOwner) {
            $activeTab = 'public';
        }

        $images = collect();
        if ($isOwner || $isPublic) {
            if ($activeTab === 'private' && $isOwner) {
                $images = $user->images()
                    ->where('privacy', 'private')
                    ->whereHas('moderation', function ($q) {
                        $q->where('status', \App\Models\Image::STATUS_APPROVED);
                    })
                    ->with(['likes', 'labelData', 'storage', 'settings'])
                    ->latest()
                    ->paginate(24);
            } elseif ($activeTab === 'saved' && $isOwner) {
                // For bookmarks, we get images user bookmarked
                $images = $user->bookmarkedImages()
                    ->whereHas('moderation', function ($q) {
                        $q->where('status', \App\Models\Image::STATUS_APPROVED);
                    })
                    ->with(['likes', 'labelData', 'storage', 'settings'])
                    ->latest('bookmarks.created_at')
                    ->paginate(24);
            } elseif ($activeTab === 'liked' && $isOwner) {
                // For liked images, we get images user liked
                $images = \App\Models\Image::whereHas('likes', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->whereHas('moderation', function ($q) {
                        $q->where('status', \App\Models\Image::STATUS_APPROVED);
                    })
                    ->with(['likes', 'labelData', 'storage', 'settings'])
                    ->latest() // ideally sort by like created_at but this is fine
                    ->paginate(24);
            } else {
                // Default: public
                $images = $user->images()
                    ->where('privacy', 'public')
                    ->whereHas('moderation', function ($q) {
                        $q->where('status', \App\Models\Image::STATUS_APPROVED);
                    })
                    ->with(['likes', 'labelData', 'storage', 'settings'])
                    ->latest()
                    ->paginate(24);
            }
        }

        $likedImageIds = [];
        $bookmarkedImageIds = [];
        if (Auth::check()) {
            $likedImageIds = \App\Models\Like::where('user_id', Auth::id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')
                ->toArray();
                
            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', Auth::id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')
                ->toArray();
        }

        return view('profile.show', [
            'profileUser' => $user,
            'images' => $images,
            'isOwner' => $isOwner,
            'isPublic' => $isPublic,
            'isFollowing' => $isFollowing,
            'likedImageIds' => $likedImageIds,
            'bookmarkedImageIds' => $bookmarkedImageIds,
            'activeTab' => $activeTab,
            'stats' => [
                'likes' => $totalLikes,
                'photos' => $totalPhotos,
                'connections' => $totalConnections,
            ]
        ]);
    }
}
