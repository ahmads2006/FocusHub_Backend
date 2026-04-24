<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AdminController extends Controller
{
    /**
     * حظر / إلغاء حظر مستخدم
     */
    public function banUser(Request $request, User $user): JsonResponse
    {
        $status = $user->userStatus ?? $user->userStatus()->create([]);
        $status->update([
            'is_banned' => true,
            'banned_at' => now(),
            'notes' => $request->input('notes', $status->notes),
        ]);

        return response()->json([
            'message' => __('User has been banned.'),
            'user' => $user->fresh(['userStatus']),
        ]);
    }

    public function unbanUser(User $user): JsonResponse
    {
        ($user->userStatus ?? $user->userStatus()->create([]))->update(['is_banned' => false, 'banned_at' => null]);

        return response()->json([
            'message' => __('User has been unbanned.'),
            'user' => $user->fresh(['userStatus']),
        ]);
    }

    /**
     * تفعيل / إلغاء الحجب الشامل (Shadow Privacy)
     */
    public function toggleShadowHidden(User $user): JsonResponse
    {
        $status = $user->userStatus ?? $user->userStatus()->create([]);
        $hidden = ! $user->is_shadow_hidden;
        $status->update(['is_shadow_hidden' => $hidden]);

        return response()->json([
            'message' => $hidden
                ? __('Shadow privacy has been enabled.')
                : __('Shadow privacy has been disabled.'),
            'user' => $user->fresh(['userStatus']),
        ]);
    }

    /**
     * عرض سجلات النشاط لمستخدم معيّن
     */
    public function userActivity(User $user): JsonResponse
    {
        $activities = Activity::where('causer_id', $user->id)
            ->with(['subject', 'causer'])
            ->latest()
            ->paginate(20);

        return response()->json($activities);
    }

    /**
     * عرض كل النشاطات (للمراقبة العامة)
     */
    public function allActivities(Request $request): JsonResponse
    {
        $query = Activity::query()->with(['subject', 'causer']);

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        $activities = $query->latest()->paginate(20);

        return response()->json($activities);
    }

    /**
     * تغيير خصوصية ألبوم
     */
    public function setAlbumPrivacy(Album $album, Request $request): JsonResponse
    {
        $request->validate(['is_private' => 'required|boolean']);

        $album->update(['is_private' => $request->boolean('is_private')]);

        return response()->json([
            'message' => __('Album privacy updated.'),
            'album' => $album->fresh(),
        ]);
    }

    /**
     * تغيير خصوصية صورة
     */
    public function setImagePrivacy(Image $image, Request $request): JsonResponse
    {
        $request->validate(['privacy' => 'required|in:public,private']);

        $image->update(['privacy' => $request->privacy]);

        return response()->json([
            'message' => __('Image privacy updated.'),
            'image' => $image->fresh(),
        ]);
    }

    /**
     * ترقية دور المستخدم (مثلاً من user إلى photographer)
     */
    public function upgradeUserRole(Request $request, User $user): JsonResponse
    {
        $validRoles = \Spatie\Permission\Models\Role::pluck('name')->toArray();
        $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', $validRoles)],
        ]);

        $role = $request->role;
        $user->syncRoles([$role]);
        $roleMap = ['super-admin' => 'super_admin', 'user' => 'user', 'photographer' => 'photographer', 'editor' => 'editor'];
        $user->forceFill(['role' => $roleMap[$role] ?? $role])->save();

        return response()->json([
            'message' => __('User role has been updated.'),
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * قائمة المستخدمين (للإدارة)
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query()->with('roles');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('banned')) {
            $query->whereHas('userStatus', fn ($q) => $q->where('is_banned', $request->boolean('banned')));
        }

        $users = $query->with('userStatus')->latest()->paginate(20);

        return response()->json($users);
    }

    /**
     * حذف أي محتوى (صورة) - للسوبر أدمن
     */
    public function deleteImage(Image $image): JsonResponse
    {
        $image->delete();

        return response()->json(['message' => __('Image deleted.')]);
    }

    /**
     * حذف أي ألبوم - للسوبر أدمن
     */
    public function deleteAlbum(Album $album): JsonResponse
    {
        $album->delete();

        return response()->json(['message' => __('Album deleted.')]);
    }
}
