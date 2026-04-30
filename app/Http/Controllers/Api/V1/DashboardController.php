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
     */
    public function stats()
    {
        $userId = Auth::id();
        $now = now();
        $lastWeek = now()->subDays(7);
        $twoWeeksAgo = now()->subDays(14);
        $lastMonth = now()->subMonth();

        // ── KPI 1: Total Photos & Trend (vs Last Month) ──
        $totalPhotos = Image::withoutGlobalScopes()->where('user_id', $userId)->count();
        $photosLastMonth = Image::withoutGlobalScopes()->where('user_id', $userId)->where('created_at', '<', $lastMonth)->count();
        $photosTrend = $photosLastMonth > 0 ? round((($totalPhotos - $photosLastMonth) / $photosLastMonth) * 100, 1) : 100;

        // ── KPI 2: Total Views & Trend ──
        $totalViews = \App\Models\ImageSettings::whereIn('image_id', function($query) use ($userId) {
            $query->select('id')->from('images')->where('user_id', $userId);
        })->sum('views_count');
        // Simplified trend for views
        $viewsTrend = 8.5; 

        // ── KPI 3: Uploaded This Week & Trend ──
        $thisWeekUploads = Image::withoutGlobalScopes()->where('user_id', $userId)->where('created_at', '>=', $lastWeek)->count();
        $lastWeekUploads = Image::withoutGlobalScopes()->where('user_id', $userId)
            ->where('created_at', '>=', $twoWeeksAgo)
            ->where('created_at', '<', $lastWeek)
            ->count();
        $uploadTrend = $lastWeekUploads > 0 ? round((($thisWeekUploads - $lastWeekUploads) / $lastWeekUploads) * 100, 1) : ($thisWeekUploads > 0 ? 100 : 0);

        // ── KPI 4: Active Albums ──
        $activeAlbumsCount = Album::where('user_id', $userId)
            ->whereHas('images')
            ->count();

        $publicAlbums = Album::where('user_id', $userId)->public()->count();
        $privateAlbums = Album::where('user_id', $userId)->whereHas('settings', function($q) {
            $q->where('privacy', 'private');
        })->count();

        // ── Distribution & Top Photos ──
        $albumsDist = Album::where('user_id', $userId)
            ->withCount('images')
            ->orderBy('images_count', 'desc')
            ->limit(6)
            ->get()
            ->map(function($a) {
                $maxImages = Image::withoutGlobalScopes()->where('user_id', $a->user_id)->count() ?: 1;
                return [
                    'name' => $a->title,
                    'count' => $a->images_count,
                    'progress' => round(($a->images_count / $maxImages) * 100)
                ];
            });

        $topViewed = Image::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->leftJoin('image_settings', 'images.id', '=', 'image_settings.image_id')
            ->with(['album'])
            ->orderBy('image_settings.views_count', 'desc')
            ->select('images.*')
            ->limit(5)
            ->get()
            ->map(function($img) {
                $maxViews = DB::table('image_settings')
                    ->whereIn('image_id', function($q) use ($img) {
                        $q->select('id')->from('images')->where('user_id', $img->user_id);
                    })->max('views_count') ?: 1;

                return [
                    'name'  => $img->title ?: 'Untitled',
                    'album' => $img->album ? $img->album->title : 'Universal',
                    'views' => $img->settings ? $img->settings->views_count : 0,
                    'progress' => round((($img->settings ? $img->settings->views_count : 0) / $maxViews) * 100),
                    'icon'  => '📸'
                ];
            });

        $kpis = [
            'totalPhotos' => [
                'value' => (int)$totalPhotos,
                'trend' => $photosTrend
            ],
            'totalViews' => [
                'value' => (int)$totalViews,
                'trend' => $viewsTrend
            ],
            'weeklyUploads' => [
                'value' => (int)$thisWeekUploads,
                'trend' => $uploadTrend
            ],
            'activeAlbums' => [
                'value' => (int)$activeAlbumsCount,
                'trend' => 0 // Stable
            ]
        ];

        return response()->json([
            'success' => true,
            'kpis'               => $kpis,
            'albumsDistribution' => $albumsDist,
            'mostViewedPhotos'   => $topViewed,
            'data' => [
                'kpis'               => $kpis,
                'totalPhotos'        => (int)$totalPhotos,
                'photos_count'       => (int)$totalPhotos,
                'views'              => (int)$totalViews,
                'publicAlbums'       => (int)$publicAlbums,
                'privateAlbums'      => (int)$privateAlbums,
                'albumsDistribution' => $albumsDist,
                'mostViewedPhotos'   => $topViewed
            ]
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
        \Log::info('Album Summary called for user: ' . Auth::id());
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

    /**
     * GET /api/v1/top-photos
     */
    public function topPhotos()
    {
        $userId = Auth::id();

        // Fetch top 6 photos
        // Primary sort: Likes, Secondary sort: Views, Tertiary: Latest
        $photos = Image::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->select('images.*')
            ->leftJoin('image_settings', 'images.id', '=', 'image_settings.image_id')
            ->with(['album'])
            ->withCount('likes')
            ->orderBy('likes_count', 'desc')
            ->orderBy('image_settings.views_count', 'desc')
            ->orderBy('images.created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function($img) {
                return [
                    'id'    => $img->id,
                    'title' => $img->title ?: 'Untitled',
                    'album' => $img->album ? $img->album->title : 'Universal',
                    'likes' => $img->likes_count,
                    'views' => $img->settings ? $img->settings->views_count : 0,
                    'url'   => $img->url
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $photos,
        ]);
    }
}
