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

        // 🚀 OPTIMIZATION: Combine into one query using aggregation if possible, 
        // or at least optimize individual counts.
        $stats = DB::table('images')
            ->where('user_id', $userId)
            ->selectRaw('count(*) as totalPhotos, sum(views_count) as views')
            ->first();

        $albumStats = DB::table('albums')
            ->where('user_id', $userId)
            ->selectRaw("count(case when privacy = 'public' then 1 end) as publicAlbums")
            ->selectRaw("count(case when privacy = 'private' then 1 end) as privateAlbums")
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'totalPhotos'   => (int) ($stats->totalPhotos ?? 0),
                'views'         => (int) ($stats->views ?? 0),
                'publicAlbums'  => (int) ($albumStats->publicAlbums ?? 0),
                'privateAlbums' => (int) ($albumStats->privateAlbums ?? 0),
            ],
        ]);
    }

    /**
     * GET /api/v1/activity-log
     */
    public function activityLog()
    {
        $userId = Auth::id();

        // Optimized activity fetching
        $recentUploads = Image::where('user_id', $userId)
            ->select('id', 'title', 'created_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($img) => [
                'id'        => $img->id,
                'type'      => 'upload',
                'title'     => 'Uploaded photo: ' . ($img->title ?? 'Untitled'),
                'timestamp' => $img->created_at->toISOString(),
            ]);

        $recentAlbums = Album::where('user_id', $userId)
            ->select('id', 'title', 'created_at')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn($album) => [
                'id'        => 'album_' . $album->id,
                'type'      => 'album',
                'title'     => 'Created album: ' . $album->title,
                'timestamp' => $album->created_at->toISOString(),
            ]);

        $sorted = $recentUploads->merge($recentAlbums)
            ->sortByDesc('timestamp')
            ->values()
            ->take(10);

        return response()->json([
            'success' => true,
            'data' => $sorted,
        ]);
    }

    /**
     * GET /api/v1/albums/summary
     */
    public function albumSummary()
    {
        $userId = Auth::id();
        $sevenDaysAgo = Carbon::now()->subDays(6)->startOfDay();

        // 🚀 OPTIMIZATION: One query for 7 days of data using groupBy
        $countsByDay = Image::where('user_id', $userId)
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

        $summary = DB::table('images')
            ->where('user_id', $userId)
            ->selectRaw('count(*) as totalPhotos')
            ->addSelect(DB::raw('(SELECT count(*) FROM albums WHERE user_id = ' . (int)$userId . ') as totalAlbums'))
            ->first();

        return response()->json([
            'success' => true,
            'totalAlbums'    => (int) ($summary->totalAlbums ?? 0),
            'totalPhotos'    => (int) ($summary->totalPhotos ?? 0),
            'uploadActivity' => $uploadActivity,
        ]);
    }
}
