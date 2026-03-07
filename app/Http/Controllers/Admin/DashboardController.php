<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'total_images' => Image::count(),
            'total_albums' => Album::count(),
            'banned_users' => User::whereHas('userStatus', fn ($q) => $q->where('is_banned', true))->count(),
            'shadow_users' => User::whereHas('userStatus', fn ($q) => $q->where('is_shadow_hidden', true))->count(),
            'recent_activities' => Activity::with(['subject', 'causer'])->latest()->take(10)->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    public function users(Request $request): View
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

        $users = $query->with('userStatus')->latest()->paginate(15)->withQueryString();
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function activities(Request $request): View
    {
        $query = Activity::query()->with(['subject', 'causer']);

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        $activities = $query->latest()->paginate(20)->withQueryString();

        return view('admin.activities.index', compact('activities'));
    }
}
