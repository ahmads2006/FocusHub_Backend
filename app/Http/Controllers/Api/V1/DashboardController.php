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
        $totalViews = Image::withoutGlobalScopes()->where('user_id', $userId)->sum('views_count');
        
        $publicAlbums = Album::where('user_id', $userId)->where('privacy', 'public')->count();
        $privateAlbums = Album::where('user_id', $userId)->where('privacy', 'private')->count();

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

        // 1. Recent Image Uploads
        $recentUploads = Image::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->select('id', 'title', 'created_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($img) => [
                'id'        => 'up_' . $img->id,
                'type'      => 'upload',
                'title'     => 'Uploaded: ' . ($img->title ?? 'New Photo'),
                'timestamp' => $img->created_at->toISOString(),
            ]);

        // 2. Recent Albums
        $recentAlbums = Album::where('user_id', $userId)
            ->select('id', 'title', 'created_at')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn($album) => [
                'id'        => 'al_' . $album->id,
                'type'      => 'album',
                'title'     => 'Created Album: ' . $album->title,
                'timestamp' => $album->created_at->toISOString(),
            ]);

        // 3. Recent Likes on user's photos (Social Activity)
        $recentLikes = DB::table('image_likes')
            ->join('images', 'image_likes.image_id', '=', 'images.id')
            ->join('users', 'image_likes.user_id', '=', 'users.id')
            ->where('images.user_id', $userId)
            ->where('image_likes.user_id', '!=', $userId) // Only others' likes
            ->select('image_likes.created_at', 'users.name as fan_name', 'images.title as img_title')
            ->latest('image_likes.created_at')
            ->limit(5)
            ->get()
            ->map(fn($like) => [
                'id'        => 'lk_' . md5($like->created_at),
                'type'      => 'share', // Using blue color for likes in UI
                'title'     => "{$like->fan_name} liked your photo \"{$like->img_title}\"",
                'timestamp' => Carbon::parse($like->created_at)->toISOString(),
            ]);

        $sorted = $recentUploads->merge($recentAlbums)->merge($recentLikes)
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
