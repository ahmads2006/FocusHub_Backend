<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;
use App\Models\UserStatus;
use App\Models\UserProfile;

class AuditVaultController extends Controller
{
    /**
     * Dashboard — Overview with stats + recent critical alerts.
     */
    public function dashboard(): View
    {
        $stats = [
            'total_users'   => User::count(),
            'total_images'  => Image::count(),
            'total_albums'  => Album::count(),
            'banned_users'  => User::whereHas('userStatus', fn ($q) => $q->where('is_banned', true))->count(),
            'total_operations' => Activity::count(),
            'today_operations' => Activity::whereDate('created_at', today())->count(),
        ];

        // Recent critical operations (deletions, bans, role changes)
        $criticalAlerts = Activity::with(['subject', 'causer'])
            ->where(function ($q) {
                $q->where('description', 'like', '%deleted%')
                  ->orWhere('description', 'like', '%banned%')
                  ->orWhere('description', 'like', '%role%')
                  ->orWhere('description', 'like', '%purged%')
                  ->orWhere('description', 'like', '%removed%');
            })
            ->latest()
            ->take(10)
            ->get();

        $recentActivities = Activity::with(['subject', 'causer'])
            ->latest()
            ->take(15)
            ->get();

        return view('vault.dashboard', compact('stats', 'criticalAlerts', 'recentActivities'));
    }

    /**
     * Operations Ledger — Full audit trail with filters & pagination.
     */
    public function operations(Request $request): View
    {
        $query = Activity::with(['subject', 'causer']);

        // Filter by operation type / description
        if ($request->filled('operation')) {
            $query->where('description', 'like', '%' . $request->operation . '%');
        }

        // Filter by username
        if ($request->filled('username')) {
            $query->whereHasMorph('causer', [User::class], function ($q) use ($request) {
                $q->where(function($sq) use ($request) {
                    $sq->where('email', 'like', '%' . $request->username . '%')
                       ->orWhereHas('profile', function ($pq) use ($request) {
                           $pq->where('name', 'like', '%' . $request->username . '%');
                       });
                });
            });
        }

        // Filter by date
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Filter by subject type (model)
        if ($request->filled('model_type')) {
            $query->where('subject_type', 'like', '%' . $request->model_type . '%');
        }

        $activities = $query->latest()->paginate(25)->withQueryString();

        // Get unique operation descriptions for filter dropdown
        $operationTypes = Activity::select('description')
            ->distinct()
            ->orderBy('description')
            ->pluck('description');

        // Critical alerts for the sidebar panel
        $criticalAlerts = Activity::with(['subject', 'causer'])
            ->where(function ($q) {
                $q->where('description', 'like', '%deleted%')
                  ->orWhere('description', 'like', '%banned%')
                  ->orWhere('description', 'like', '%role%')
                  ->orWhere('description', 'like', '%removed%');
            })
            ->latest()
            ->take(5)
            ->get();

        return view('vault.operations', compact('activities', 'operationTypes', 'criticalAlerts'));
    }

    /**
     * Users Audit — User listing with activity counts.
     */
    public function users(Request $request): View
    {
        $query = User::with(['userStatus', 'profile', 'roles']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhereHas('profile', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'banned' => $query->whereHas('userStatus', fn ($q) => $q->where('is_banned', true)),
                'shadow' => $query->whereHas('userStatus', fn ($q) => $q->where('is_shadow_hidden', true)),
                'active' => $query->whereHas('userStatus', fn ($q) => $q->where('is_banned', false)->where('is_shadow_hidden', false)),
                default  => null,
            };
        }

        $users = $query->latest()->paginate(20)->withQueryString();

        return view('vault.users', compact('users'));
    }

    /**
     * Security — Critical operations feed.
     */
    public function security(Request $request): View
    {
        $query = Activity::with(['subject', 'causer']);

        // Only show security-related events
        $query->where(function ($q) {
            $q->where('description', 'like', '%deleted%')
              ->orWhere('description', 'like', '%banned%')
              ->orWhere('description', 'like', '%unbanned%')
              ->orWhere('description', 'like', '%role%')
              ->orWhere('description', 'like', '%purged%')
              ->orWhere('description', 'like', '%removed%')
              ->orWhere('description', 'like', '%shadow%')
              ->orWhere('description', 'like', '%login%')
              ->orWhere('description', 'like', '%password%');
        });

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        $alerts = $query->latest()->paginate(30)->withQueryString();

        return view('vault.security', compact('alerts'));
    }
}
