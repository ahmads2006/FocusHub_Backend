<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/stats
     * Get photographer dashboard statistics.
     */
    /**
     * GET /api/v1/stats
     * Get photographer dashboard statistics.
     */
    public function stats()
    {
        $userId = Auth::id();

        // Using Eloquent models to respect UUIDs and any potential model-level logic.
        $totalPhotos = Image::withoutGlobalScopes()->where('user_id', $userId)->count();
        
        // views_count was moved to image_settings table in v22.0
        $totalViews = DB::table('images')
            ->join('image_settings', 'images.id', '=', 'image_settings.image_id')
            ->where('images.user_id', $userId)
            ->sum('image_settings.views_count');
        
        // Privacy is stored in album_settings
        $publicAlbums = Album::where('user_id', $userId)
            ->whereHas('settings', fn($q) => $q->where('privacy', 'public'))
            ->count();
            
        $privateAlbums = Album::where('user_id', $userId)
            ->whereHas('settings', fn($q) => $q->where('privacy', 'private'))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'totalPhotos'   => (int) $totalPhotos,
                'views'         => (int) $totalViews,
                'publicAlbums'  => (int) $publicAlbums,
                'privateAlbums' => (int) $privateAlbums,
                'photos_count'  => (int) $totalPhotos, // Compatibility fallback
            ],
        ]);
    }

    /**
     * GET /api/v1/activity-log
     */
    public function activityLog()
    {
        $userId = Auth::id();

        // Using Spatie ActivityLog for comprehensive activity tracking
        $activities = \Spatie\Activitylog\Models\Activity::where(function($q) use ($userId) {
                $q->where('causer_id', $userId)
                  ->orWhere(function($sq) use ($userId) {
                      // Also include activities where the user is the subject (e.g. someone liked their photo)
                      $sq->where('subject_type', Image::class)
                         ->whereIn('subject_id', function($sub) use ($userId) {
                             $sub->select('id')->from('images')->where('user_id', $userId);
                         });
                  });
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(function($act) {
                $type = 'upload';
                $color = 'accent';
                $title = $act->description;

                // Map Spatie events to UI types and descriptive titles
                if ($act->subject_type === Image::class) {
                    if ($act->event === 'created') {
                        $title = "Uploaded a new photo";
                    } elseif ($act->event === 'deleted') {
                        $type = 'security';
                        $color = 'red';
                        $title = "Deleted a photo";
                    }
                } elseif ($act->subject_type === Album::class) {
                    $type = 'album';
                    $color = 'purple';
                    if ($act->event === 'created') {
                        $title = "Created a new album";
                    } elseif ($act->event === 'updated' && str_contains($act->description, 'collaborator')) {
                        $title = "Added a member to album";
                    }
                }

                // Social activity
                if (str_contains($act->description, 'liked')) {
                    $type = 'share';
                    $color = 'blue';
                }

                return [
                    'id'        => $act->id,
                    'type'      => $type,
                    'title'     => $title,
                    'timestamp' => $act->created_at->toISOString(),
                    'color'     => $color
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * GET /api/v1/albums/summary
     */
    public function albumSummary()
    {
        $userId = Auth::id();
        $sevenDaysAgo = Carbon::now()->subDays(6)->startOfDay();

        $countsByDay = Image::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->pluck('count', 'date');

        $uploadActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $formattedDate = Carbon::parse($date)->format('D');
            $uploadActivity[] = [
                'day'   => $formattedDate,
                'count' => $countsByDay[$date] ?? 0,
            ];
        }

        $totalPhotos = Image::withoutGlobalScopes()->where('user_id', $userId)->count();
        $totalAlbums = Album::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'totalAlbums'    => (int) $totalAlbums,
            'totalPhotos'    => (int) $totalPhotos,
            'uploadActivity' => $uploadActivity,
        ]);
    }
}

