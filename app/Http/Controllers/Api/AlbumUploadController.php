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
            // Typically allow zip, rar, and 7z. Max 500MB
            'archive' => 'required|file|mimes:zip,rar,7z|max:512000',
        ]);

        $user = $request->user() ?: \App\Models\User::first(); // Fallback for dev if needed
        $albumId = $request->album_id;

        // If no album_id, create one using album_name or default
        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title' => $request->album_name ?: 'Bulk Upload ' . date('Y-m-d H:i'),
                'privacy' => 'public', // Default to public so images show in gallery
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
            'rejected_items' => 0,
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
        $rejected = $decoded['rejected_items'] ?? 0;
        $failed = $decoded['failed_items'] ?? 0;
        
        $percentage = 0;
        if ($total > 0) {
            $percentage = round((($processed + $rejected + $failed) / $total) * 100);
        }

        $decoded['percentage'] = $percentage;

        return response()->json($decoded);
    }

    /**
     * ── Array-Based Batch Upload (Fast Response Pattern) ─────────
     *
     * Accepts images[] array via Dropzone/FilePond.
     * Validates, stores to S3 immediately, dispatches moderation jobs.
     * Returns 200 Success instantly so UI progress bar moves forward.
     * One request with N images = 1 rate limiter hit.
     */
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'album_id'   => 'nullable|exists:albums,id',
            'album_name' => 'nullable|string|max:255',
            'images'     => 'required|array|min:1|max:100',
            'images.*'   => 'required|image|mimes:jpeg,png,jpg,webp|max:10240', // 10MB each
        ]);

        $user = $request->user() ?: \App\Models\User::first();
        $albumId = $request->album_id;

        // Create album if needed
        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title'   => $request->album_name ?: 'Batch Upload ' . date('Y-m-d H:i'),
                'privacy' => 'public',
            ]);
            $albumId = $album->id;
        }

        $jobId = Str::uuid()->toString();
        $files = $request->file('images');
        $totalFiles = count($files);

        // Initialize Redis progress tracker
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        Redis::set($redisKey, json_encode([
            'total_items'    => $totalFiles,
            'processed_items' => 0,
            'rejected_items'  => 0,
            'failed_items'    => 0,
            'status'          => 'uploading',
        ]), 'EX', 86400);

        $uploadedImages = [];

        foreach ($files as $file) {
            $filename = $file->getClientOriginalName();
            $uid = Str::uuid()->toString();
            $extension = $file->getClientOriginalExtension() ?: strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $newFilename = $uid . '_' . $filename; // or $uid . '.' . $extension

            // ── Dynamic S3 Folder Structure ──
            $year = date('Y');
            $month = date('m');
            $userId = $user->id;
            $uploadedImages[] = [
            'file' => $file,
            'filename' => $filename,
            'uid' => $uid,
            'extension' => $extension,
            'newFilename' => $newFilename,
            'year' => $year,
            'month' => $month,
            'userId' => $userId,
          ];
            // ── Dynamic S3 Folder Structure ──
            $dynamicPath = "photos/{$year}/{$month}/{$userId}". $albumId ."/". $filename;

            // ── Store to S3/LocalStack (immediate) using putFileAs ──
            $s3Path = \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs($dynamicPath, $file, $newFilename);

            // ── Create DB record with "pending" moderation ──
            $album = \App\Models\Album::find($albumId);
            $inheritedPrivacy = $album ? $album->privacy : 'private';

            $image = \App\Models\Image::create([
                'album_id'   => $albumId,
                'user_id'    => $user->id,
                'title'      => pathinfo($filename, PATHINFO_FILENAME),
                'filename'   => $filename,
                'file_type'  => $extension,
                'size'       => $file->getSize(),
                'privacy'    => $inheritedPrivacy,
            ]);

            $image->storage()->updateOrCreate(['image_id' => $image->id], [
                'original_path' => $s3Path,
                'path'          => $s3Path,
                'md5_hash'      => md5_file($file->getRealPath()),
            ]);

            $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                'status'      => 'pending',
                'is_sensitive' => false,
                'is_visible'   => true,
            ]);

            // ── Dispatch background moderation job to Redis queue ──
            \App\Jobs\ProcessImageModeration::dispatch($image->id, $jobId, $s3Path);

            $uploadedImages[] = [
                'id'       => $image->id,
                'filename' => $filename,
            ];
        }

        // Update status to "processing" (files are uploaded, moderation is running)
        $data = json_decode(Redis::get($redisKey), true);
        $data['status'] = 'processing';
        Redis::set($redisKey, json_encode($data), 'EX', 86400);

        // ── Immediate 200 Response (Fast Response Pattern) ──
        return response()->json([
            'message'  => 'تم رفع الصور بنجاح. جاري تحليلها بالذكاء الاصطناعي.',
            'job_id'   => $jobId,
            'album_id' => $albumId,
            'total'    => $totalFiles,
            'images'   => $uploadedImages,
            'status'   => 'processing',
        ]);
    }

    /**
     * ── Album Moderation Status (Polling Endpoint) ──────────────
     *
     * Returns real-time moderation progress for all images in an album.
     * The frontend can poll this to show a progress bar that reaches 100%
     * only when all AI moderation is truly finished.
     */
    public function getAlbumStatus($albumId)
    {
        $album = \App\Models\Album::findOrFail($albumId);

        $total     = $album->images()->withoutGlobalScopes()->count();
        $approved  = $album->images()->withoutGlobalScopes()
            ->whereHas('moderation', fn($q) => $q->where('status', 'approved'))->count();
        $pending   = $album->images()->withoutGlobalScopes()
            ->whereHas('moderation', fn($q) => $q->where('status', 'pending'))->count();
        $rejected  = $album->images()->withoutGlobalScopes()
            ->whereHas('moderation', fn($q) => $q->where('status', 'rejected'))->count();

        $processed = $approved + $rejected;
        $percentage = $total > 0 ? round(($processed / $total) * 100) : 100;

        return response()->json([
            'album_id'   => $albumId,
            'total'      => $total,
            'approved'   => $approved,
            'pending'    => $pending,
            'rejected'   => $rejected,
            'percentage' => $percentage,
            'status'     => $pending > 0 ? 'processing' : 'completed',
        ]);
    }
}
