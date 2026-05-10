<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Services\Core\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's full profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['profile', 'roles']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatProfile($user),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user    = $request->user();
        $profile = $user->profile;

        // Check username 30-day change limit
        if ($request->has('username') && $request->input('username') !== $profile->username) {
            $lastChanged = $profile->username_last_changed_at;
            if ($lastChanged && $lastChanged->diffInDays(now()) < 30) {
                $daysRemaining = 30 - $lastChanged->diffInDays(now());
                return response()->json([
                    'success' => false,
                    'message' => "لا يمكنك تغيير اسم المستخدم إلا مرة واحدة كل 30 يومًا. يتبقى $daysRemaining أيام.",
                ], 422);
            }
            $profile->username_last_changed_at = now();
        }

        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.profile_updated'),
            'data'    => $this->formatProfile($user->fresh(['profile', 'roles'])),
        ]);
    }

    /**
     * Update security settings.
     */
    public function updateSecurity(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->stay_logged_in = $request->boolean('stay_logged_in');
        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.security_updated'),
        ]);
    }

    /**
     * Update photography & watermark preferences.
     */
    public function updatePhotography(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'dynamic_watermark'    => ['boolean'],
            'auto_orient_default'  => ['boolean'],
            'is_public_profile'    => ['boolean'],
            'watermark_mode'       => ['nullable', 'string', 'in:text,logo'],
            'watermark_text'       => ['nullable', 'string', 'max:50'],
            'watermark_logo'       => ['nullable', 'image', 'max:1024'],
            'watermark_text_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'watermark_neon_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'watermark_opacity'    => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $user->dynamic_watermark   = $request->boolean('dynamic_watermark');
        $user->auto_orient_default = $request->boolean('auto_orient_default');
        $user->is_public_profile   = $request->boolean('is_public_profile');

        if ($request->filled('watermark_mode'))       $user->watermark_mode       = $validated['watermark_mode'];
        if ($request->filled('watermark_text'))        $user->watermark_text       = $validated['watermark_text'];
        if ($request->filled('watermark_text_color'))  $user->watermark_text_color = $validated['watermark_text_color'];
        if ($request->filled('watermark_neon_color'))  $user->watermark_neon_color = $validated['watermark_neon_color'];
        if ($request->has('watermark_opacity'))         $user->watermark_opacity    = $validated['watermark_opacity'];

        // Handle Logo Upload
        if ($request->hasFile('watermark_logo')) {
            if ($user->watermark_logo) {
                Storage::disk('public')->delete($user->watermark_logo);
            }
            $path = $request->file('watermark_logo')->store('watermarks', 'public');
            $user->watermark_logo = $path;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.shooting_updated'),
            'data'    => $this->formatProfile($user->fresh(['profile', 'roles'])),
        ]);
    }

    /**
     * Update user avatar.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|max:5120',
        ]);

        $user    = $request->user();
        $service = app(ImageService::class);
        $path    = $service->generateAvatar($request->file('profile_picture'));

        if ($user->profile_picture && !str_contains($user->profile_picture, 'ui-avatars.com')) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $user->update(['profile_picture' => $path]);

        return response()->json([
            'success' => true,
            'message' => __('messages.profile_picture_updated'),
            'data'    => ['avatar' => $user->avatar],
        ]);
    }

    /**
     * Delete user account.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => __('messages.account_deleted'),
        ]);
    }

    /**
     * Get storage drive stats.
     */
    public function driveStats(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data'    => [
                'storage_used_bytes'   => $user->storage_used_bytes,
                'storage_limit_bytes'  => $user->storage_limit_bytes,
                'storage_used_percent' => $user->storage_used_percentage,
                'is_unlimited'         => $user->storage_limit_bytes === null,
            ],
        ]);
    }

    /**
     * Display a photographer's public profile.
     */
    public function showPhotographer(Request $request, User $user): JsonResponse
    {
        $isOwner     = Auth::id() === $user->id;
        $isPublic    = (bool) $user->is_public_profile;
        $isFollowing = false;

        if (Auth::check() && !$isOwner) {
            $isFollowing = \App\Models\Connection::where('user_id', Auth::id())
                ->where('connected_user_id', $user->id)
                ->where('status', 'accepted')
                ->exists();
        }

        // Stats: Always query real DB for accuracy
        $totalPhotos = $user->images()->count();
        $totalLikes = \App\Models\Like::whereHas('image', fn($q) => $q->where('user_id', $user->id))->count();
        // Followers = people who follow THIS user
        $totalConnections = \App\Models\Connection::where('connected_user_id', $user->id)
            ->where('status', 'accepted')
            ->count();

        $tab = $request->get('tab', 'public');
        if (!$isOwner) $tab = 'public';

        $images = collect();
        if ($isOwner || $isPublic) {
            $query = $user->images()->with(['labelData', 'storage', 'settings'])->withCount(['likes', 'bookmarks']);

            if ($tab === 'private' && $isOwner) {
                $query->where('privacy', 'private');
                if (!$isOwner) {
                    $query->whereHas('moderation', fn($q) => $q->where('status', \App\Models\Image::STATUS_APPROVED));
                }
            } elseif ($tab === 'saved' && $isOwner) {
                $images = $user->bookmarkedImages()
                    ->where(function ($q) use ($user) {
                        $q->where('images.privacy', 'public')
                          ->orWhere('images.user_id', $user->id);
                    })
                    ->whereHas('moderation', fn($q) => $q->where('status', \App\Models\Image::STATUS_APPROVED))
                    ->with(['user', 'labelData', 'storage', 'settings', 'aiMetadata'])
                    ->withCount(['likes', 'bookmarks'])
                    ->latest('bookmarks.created_at')
                    ->paginate(24);
            } elseif ($tab === 'liked' && $isOwner) {
                $images = \App\Models\Image::whereHas('likes', fn($q) => $q->where('user_id', $user->id))
                    ->where(function ($q) use ($user) {
                        $q->where('images.privacy', 'public')
                          ->orWhere('images.user_id', $user->id);
                    })
                    ->whereHas('moderation', fn($q) => $q->where('status', \App\Models\Image::STATUS_APPROVED))
                    ->with(['user', 'labelData', 'storage', 'settings', 'aiMetadata'])
                    ->withCount(['likes', 'bookmarks'])
                    ->latest()
                    ->paginate(24);
            } else {
                $query->where('privacy', 'public');
                if (!$isOwner) {
                    $query->whereHas('moderation', fn($q) => $q->where('status', \App\Models\Image::STATUS_APPROVED));
                }
            }

            if ($images->isEmpty() && isset($query)) {
                $images = $query->latest()->paginate(24);
            }
        }

        $formattedImages = \App\Http\Resources\PhotoResource::collection($images);

        // Liked & Bookmarked state for current viewer
        $likedImageIds = [];
        $bookmarkedImageIds = [];
        if (Auth::check()) {
            $likedImageIds = \App\Models\Like::where('user_id', Auth::id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')->toArray();

            $bookmarkedImageIds = \App\Models\Bookmark::where('user_id', Auth::id())
                ->whereIn('image_id', $images->pluck('id'))
                ->pluck('image_id')->toArray();
        }

        $images->getCollection()->transform(function ($image) use ($likedImageIds, $bookmarkedImageIds) {
            $image->is_liked = in_array($image->id, $likedImageIds);
            $image->is_saved = in_array($image->id, $bookmarkedImageIds);
            // Ensure counts are preserved on the model instance
            $image->likes_count = $image->likes_count ?? 0;
            $image->bookmarks_count = $image->bookmarks_count ?? 0;
            return $image;
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'user'        => [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'avatar'            => $user->avatar,
                    'is_badge_verified' => (bool) $user->is_badge_verified,
                    'is_public_profile' => $isPublic,
                ],
                'is_owner'             => $isOwner,
                'is_following'         => $isFollowing,
                'stats'                => [
                    'likes'       => $totalLikes,
                    'photos'      => $totalPhotos,
                    'connections' => $totalConnections,
                ],
                'images'               => \App\Http\Resources\PhotoResource::collection($images)->response()->getData(true),
                'liked_image_ids'      => $likedImageIds,
                'bookmarked_image_ids' => $bookmarkedImageIds,
                'active_tab'           => $tab,
            ],
        ]);
    }

    /**
     * Format user data for profile responses.
     */
    private function formatProfile(User $user): array
    {
        return [
            'id'                   => $user->id,
            'name'                 => $user->name,
            'email'                => $user->email,
            'avatar'               => $user->avatar,
            'is_verified'          => (bool) $user->is_verified,
            'is_badge_verified'    => (bool) $user->is_badge_verified,
            'role'                 => $user->role,
            'roles'                => $user->roles->pluck('name'),
            'is_public_profile'    => (bool) $user->is_public_profile,
            'dynamic_watermark'    => (bool) $user->dynamic_watermark,
            'auto_orient_default'  => (bool) $user->auto_orient_default,
            'watermark_mode'       => $user->watermark_mode,
            'watermark_text'       => $user->watermark_text,
            'watermark_text_color' => $user->watermark_text_color,
            'watermark_neon_color' => $user->watermark_neon_color,
            'watermark_opacity'    => $user->watermark_opacity,
            'storage_used_bytes'   => $user->storage_used_bytes,
            'storage_limit_bytes'  => $user->storage_limit_bytes,
            'profile'              => $user->profile,
            'created_at'           => $user->created_at,
        ];
    }

    /**
     * Get notification preferences for the user.
     */
    public function getNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        // Default preferences if null
        $preferences = $user->notification_preferences ?? [
            'email_marketing' => true,
            'email_security' => true,
            'push_likes' => true,
            'push_comments' => true,
            'push_follows' => true,
        ];

        return response()->json([
            'success' => true,
            'data'    => $preferences,
        ]);
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'email_marketing' => 'boolean',
            'email_security'  => 'boolean',
            'push_likes'      => 'boolean',
            'push_comments'   => 'boolean',
            'push_follows'    => 'boolean',
        ]);

        $currentPreferences = $user->notification_preferences ?? [];
        $user->notification_preferences = array_merge($currentPreferences, $validated);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => __('messages.notifications_updated'),
            'data'    => $user->notification_preferences,
        ]);
    }

    /**
     * Export user data (GDPR Compliance).
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // In a real scenario, dispatch a job to gather all data, zip it, and email the user.
        // Dispatch(new ExportUserDataJob($user));

        return response()->json([
            'success' => true,
            'message' => __('messages.export_requested'),
        ]);
    }

    /**
     * Get user analytics dashboard data.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Aggregate stats across all user images
        $totalPhotos = $user->images()->count();
        $totalLikes = \App\Models\Like::whereHas('image', fn($q) => $q->where('user_id', $user->id))->count();
        
        // Sum of views from the image_settings relationship
        $totalViews = \App\Models\ImageSettings::whereHas('image', fn($q) => $q->where('user_id', $user->id))->sum('views_count');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_photos' => $totalPhotos,
                'total_likes'  => $totalLikes,
                'total_views'  => (int) $totalViews,
                'period'       => 'all_time'
            ],
        ]);
    }

    /**
     * Search for users by name or username.
     */
    public function search(Request $request): JsonResponse
    {
        $rawQuery = $request->query('query');
        if (!$rawQuery || strlen($rawQuery) < 2) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Sanitize: strip '@' prefix/suffix for better username matching
        $query = trim($rawQuery, '@');
        $userId = Auth::id();
        
        $user = Auth::user();
        
        // 1. Get blocked IDs (bidirectional)
        $blockedIds = [];
        if ($user) {
            $blockedIds = \App\Models\Block::where('sender_id', $userId)
                ->orWhere('blocked_id', $userId)
                ->get()
                ->flatMap(fn($b) => [$b->sender_id, $b->blocked_id])
                ->unique()
                ->toArray();
        }

        // 2. Get connected IDs to prioritize them
        $connectedIds = ($user && method_exists($user, 'acceptedConnections')) 
            ? $user->acceptedConnections()->pluck('id')->toArray() 
            : [];

        // 3. Perform search
        $users = User::where(function($q) use ($query) {
                $q->whereHas('profile', function($q) use ($query) {
                      $q->where('name', 'like', "%{$query}%")
                        ->orWhere('username', 'like', "%{$query}%");
                  });
            })
            ->where('id', '!=', $userId)
            ->whereNotIn('id', $blockedIds)
            ->with('profile')
            ->get();

        // 4. Sort: Connected users first
        $sortedUsers = $users->sortByDesc(fn($u) => in_array($u->id, $connectedIds))
            ->take(20)
            ->values();

        $data = $sortedUsers->map(fn($user) => [
            'id' => $user->id,
            'name' => $user->profile?->name ?? 'User',
            'username' => $user->profile?->username,
            'avatar' => $user->avatar,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get suggested users for inviting to albums or starting chats.
     * Priority: Users already connected to.
     * Fallback: Random real users.
     */
    public function suggestions(): JsonResponse
    {
        $userId = Auth::id();
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // 1. Get blocked users IDs to exclude them
        $blockedIds = \App\Models\Block::where('sender_id', $userId)
            ->orWhere('blocked_id', $userId)
            ->get()
            ->flatMap(fn($b) => [$b->sender_id, $b->blocked_id])
            ->unique()
            ->toArray();

        // 2. Get accepted connections
        $connectedIds = method_exists($user, 'acceptedConnections') 
            ? $user->acceptedConnections()->pluck('id')->toArray() 
            : [];

        // 3. Prepare suggestions
        // First, people you are already connected with
        $suggestedUsers = User::whereIn('id', $connectedIds)
            ->whereNotIn('id', $blockedIds)
            ->with('profile')
            ->take(10)
            ->get();

        // 4. Fallback: If not enough connections, add random users
        if ($suggestedUsers->count() < 10) {
            $excludeIds = array_merge($blockedIds, $connectedIds, [$userId]);
            $randomUsers = User::whereNotIn('id', $excludeIds)
                ->with('profile')
                ->inRandomOrder()
                ->take(10 - $suggestedUsers->count())
                ->get();
            
            $suggestedUsers = $suggestedUsers->concat($randomUsers);
        }

        $data = $suggestedUsers->map(fn($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->profile?->username,
            'avatar' => $user->avatar,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
