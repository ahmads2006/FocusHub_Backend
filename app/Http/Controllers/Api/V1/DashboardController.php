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
    public function stats()
    {
        $userId = Auth::id();

        $totalPhotos = Image::where('user_id', $userId)->count();
        $views = Image::where('user_id', $userId)->sum('views_count');
        $publicAlbums = Album::where('user_id', $userId)->where('privacy', 'public')->count();
        $privateAlbums = Album::where('user_id', $userId)->where('privacy', 'private')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'totalPhotos'   => $totalPhotos,
                'views'         => (int) $views,
                'publicAlbums'  => $publicAlbums,
                'privateAlbums' => $privateAlbums,
            ],
        ]);
    }

    /**
     * GET /api/v1/activity-log
     * Get recent activity log for the authenticated user.
     */
    public function activityLog()
    {
        $userId = Auth::id();

        // Use Spatie Activity Log if available, otherwise build from images/albums
        $activities = collect();

        // Recent uploads
        $recentUploads = Image::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($img) => [
                'id'        => $img->id,
                'type'      => 'upload',
                'title'     => 'Uploaded photo: ' . ($img->title ?? 'Untitled'),
                'timestamp' => $img->created_at->toISOString(),
            ]);

        $activities = $activities->merge($recentUploads);

        // Recent album creations
        $recentAlbums = Album::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($album) => [
                'id'        => 'album_' . $album->id,
                'type'      => 'album',
                'title'     => 'Created album: ' . $album->title,
                'timestamp' => $album->created_at->toISOString(),
            ]);

        $activities = $activities->merge($recentAlbums);

        // Sort by timestamp descending
        $sorted = $activities->sortByDesc('timestamp')->values()->take(10);

        return response()->json([
            'success' => true,
            'data' => $sorted,
        ]);
    }

    /**
     * GET /api/v1/albums/summary
     * Get album summary with 7-day upload activity chart data.
     */
    public function albumSummary()
    {
        $userId = Auth::id();

        // Build 7-day upload activity
        $uploadActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = Image::where('user_id', $userId)
                ->whereDate('created_at', $date->toDateString())
                ->count();

            $uploadActivity[] = [
                'day'   => $date->format('D'),
                'count' => $count,
            ];
        }

        $totalAlbums = Album::where('user_id', $userId)->count();
        $totalPhotos = Image::where('user_id', $userId)->count();

        return response()->json([
            'success' => true,
            'totalAlbums'    => $totalAlbums,
            'totalPhotos'    => $totalPhotos,
            'uploadActivity' => $uploadActivity,
        ]);
    }
}
