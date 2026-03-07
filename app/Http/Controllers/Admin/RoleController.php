<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::orderBy('name')->get()->map(function ($role) {
            $role->users_count = $role->users()->count();
            return $role;
        });

        return view('admin.roles.index', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
        ]);

        Role::create([
            'name' => $request->name,
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        return back()->with('success', 'تم إضافة الدور "' . $request->name . '"');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $name = $role->name;
        $role->delete();

        return back()->with('success', 'تم حذف الدور "' . $name . '"');
    }
}
