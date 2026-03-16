<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ExtractArchiveJob;

class AlbumUploadController extends Controller
{
    /**
     * Show the Dedicated Bulk Upload Page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            $user = \App\Models\User::first(); // Fallback for dev environment
        }
        
        $ownedAlbums = \App\Models\Album::where('user_id', $user->id)->get();
        
        return view('albums.bulk-upload', compact('ownedAlbums'));
    }

    /**
     * Handle the Bulk Upload Zip/Rar File.
     */
    public function uploadAlbum(Request $request)
    {
        $request->validate([
            'album_id' => 'nullable|exists:albums,id',
            'album_name' => 'nullable|string|max:255',
            // Typically allow zip. Rar can be processed but requires standard zip structure for safety or ext-rar.
            'archive' => 'required|file|max:512000', // 500MB max
        ]);

        $user = $request->user() ?: \App\Models\User::first(); // Fallback for dev if needed
        $albumId = $request->album_id;

        // If no album_id, create one using album_name or default
        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title' => $request->album_name ?: 'Bulk Upload ' . date('Y-m-d H:i'),
                'privacy' => 'private',
            ]);
            $albumId = $album->id;
        }

        $file = $request->file('archive');
        $jobId = Str::uuid()->toString();

        // Save safely to quarantine locally
        $path = $file->storeAs('quarantine/archives', $jobId . '.' . $file->getClientOriginalExtension(), 'local');

        // Initialize progress
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        Redis::set($redisKey, json_encode([
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'status' => 'extracting'
        ]), 'EX', 86400);

        // Dispatch extraction pipeline to background
        ExtractArchiveJob::dispatch($path, $jobId, (string) $albumId, (string) $user->id);

        return response()->json([
            'message' => 'Upload initiated successfully.',
            'job_id' => $jobId,
            'status' => 'extracting',
            'album_id' => $albumId
        ]);
    }

    /**
     * Frontend polling API for progress.
     */
    public function getUploadProgress($jobId)
    {
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        $data = Redis::get($redisKey);

        if (!$data) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Job not found or expired.'
            ], 404);
        }

        $decoded = json_decode($data, true);
        
        $total = $decoded['total_items'] ?? 0;
        $processed = $decoded['processed_items'] ?? 0;
        $failed = $decoded['failed_items'] ?? 0;
        
        $percentage = 0;
        if ($total > 0) {
            $percentage = round((($processed + $failed) / $total) * 100);
        }

        $decoded['percentage'] = $percentage;

        return response()->json($decoded);
    }
}
