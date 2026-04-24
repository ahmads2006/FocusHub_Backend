<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const ROLE_MAP = [
        'super-admin' => 'super_admin',
        'user' => 'user',
        'photographer' => 'photographer',
        'editor' => 'editor',
    ];

    public function ban(Request $request, User $user): RedirectResponse
    {
        $status = $user->userStatus ?? $user->userStatus()->create([]);
        $status->update([
            'is_banned' => true,
            'banned_at' => now(),
            'notes' => $request->input('notes', $status->notes),
        ]);
        return back()->with('success', 'تم حظر المستخدم.');
    }

    public function unban(User $user): RedirectResponse
    {
        ($user->userStatus ?? $user->userStatus()->create([]))->update(['is_banned' => false, 'banned_at' => null]);
        return back()->with('success', 'تم إلغاء حظر المستخدم.');
    }

    public function toggleShadow(Request $request, User $user): RedirectResponse
    {
        $status = $user->userStatus ?? $user->userStatus()->create([]);
        $hidden = ! $user->is_shadow_hidden;
        $status->update([
            'is_shadow_hidden' => $hidden,
            'notes' => $request->input('notes', $status->notes),
        ]);
        $msg = $hidden ? 'تم تفعيل الحجب الشامل.' : 'تم إلغاء الحجب الشامل.';
        return back()->with('success', $msg);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validRoles = Role::pluck('name')->toArray();
        $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', $validRoles)],
        ]);
        $role = $request->role;
        $user->syncRoles([$role]);
        $user->forceFill(['role' => self::ROLE_MAP[$role] ?? $role])->save();
        return back()->with('success', 'تم تحديث الدور.');
    }

    public function updateNotes(Request $request, User $user): RedirectResponse
    {
        ($user->userStatus ?? $user->userStatus()->create([]))->update(['notes' => $request->input('notes')]);
        return back()->with('success', 'تم حفظ الملاحظات.');
    }
}
