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
            return response()->json(['message' => 'Unauthenticated.'], 401);
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

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        
        // ── Rate Limiting: Max 20 Albums Uploads Per Hour ──
        if (!$user->hasRole('super_admin') && !$user->hasRole('super-admin')) {
            $executed = \Illuminate\Support\Facades\RateLimiter::attempt(
                'album-upload-limit:' . $user->id,
                20,
                function () {},
                3600
            );

            if (!$executed) {
                return response()->json([
                    'message' => __('messages.upload_limit_albums')
                ], 429);
            }
        }

        $file = $request->file('archive');

        // ── Storage Quota Check (5GB Drive System) ──
        if (!$user->hasEnoughStorage($file->getSize())) {
            return response()->json([
                'message' => __('messages.storage_limit_archive')
            ], 403);
        }

        $albumId = $request->album_id;

        // If no album_id, create one using album_name or default
        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title' => $request->album_name ?: 'Bulk Upload ' . date('Y-m-d H:i'),
                'privacy' => 'public', // Default to public so images show in gallery
            ]);
            $albumId = $album->id;
        } else {
            // 🛡️ SECURITY: Verify user has upload permission to this album
            $album = \App\Models\Album::findOrFail($albumId);
            $this->authorize('uploadPhoto', $album);
        }

        $file = $request->file('archive');
        $jobId = Str::uuid()->toString();

        // Save safely to quarantine locally
        $path = $file->storeAs('quarantine/archives', $jobId . '.' . $file->getClientOriginalExtension(), 'local');

        // Initialize progress (Resilient to Redis failure)
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        try {
            Redis::set($redisKey, json_encode([
                'total_items' => 0,
                'processed_items' => 0,
                'rejected_items' => 0,
                'failed_items' => 0,
                'status' => 'extracting'
            ]), 'EX', 86400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Redis failure in uploadAlbum: " . $e->getMessage());
        }

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
        try {
            $data = Redis::get($redisKey);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in getUploadProgress: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => __('messages.progress_unavailable')
            ]);
        }

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
            'album_id'    => 'nullable|exists:albums,id',
            'album_name'  => 'nullable|string|max:255',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'privacy'     => 'nullable|in:public,private',
            'images'      => 'required|array|min:1|max:100',
            'images.*'    => 'required|mimes:jpeg,png,jpg,webp,gif,heic,heif,tiff,tif,bmp,svg,jfif,pjpeg,pjp|max:25600',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // ── Rate Limiting ──
        if (!$user->hasRole('super_admin') && !$user->hasRole('super-admin')) {
            $executed = \Illuminate\Support\Facades\RateLimiter::attempt(
                'album-upload-limit:' . $user->id,
                20,
                function () {},
                3600
            );

            if (!$executed) {
                return response()->json(['message' => __('messages.upload_limit_batches')], 429);
            }
        }

        $albumId = $request->album_id;

        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title'   => $request->album_name ?: 'Batch Upload ' . date('Y-m-d H:i'),
                'privacy' => $request->privacy ?: 'public',
            ]);
            $albumId = $album->id;
        } else {
            $album = \App\Models\Album::findOrFail($albumId);
            $this->authorize('uploadPhoto', $album);
        }

        $files = $request->file('images');
        $totalFiles = count($files);

        // ── Storage Quota Check ──
        $totalBatchSize = array_reduce($files, fn($carry, $f) => $carry + $f->getSize(), 0);
        if (!$user->hasEnoughStorage($totalBatchSize)) {
            return response()->json(['message' => __('messages.storage_limit_batch')], 403);
        }

        $jobId = (string) Str::uuid();

        // Initialize Redis progress tracker
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        try {
            Redis::set($redisKey, json_encode([
                'total_items'    => $totalFiles,
                'processed_items' => 0,
                'rejected_items'  => 0,
                'failed_items'    => 0,
                'status'          => 'uploading',
            ]), 'EX', 86400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in uploadBatch init: " . $e->getMessage());
        }

        $uploadedImages = [];
        $batchTitle = $request->title;
        $batchDescription = $request->description;
        $batchPrivacy = $request->privacy ?: ($album ? $album->privacy : 'public');

        foreach ($files as $index => $file) {
            $filename = $file->getClientOriginalName();
            $uid = (string) Str::uuid();
            $extension = $file->getClientOriginalExtension() ?: strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $newFilename = $uid . '_' . $filename;

            $year = date('Y');
            $month = date('m');
            $dynamicPath = "photos/{$year}/{$month}/{$user->id}/{$albumId}";

            // ── Store to S3 ──
            $s3Path = \Illuminate\Support\Facades\Storage::disk('s3')->putFileAs($dynamicPath, $file, $newFilename, ['visibility' => 'public']);

            try {
                $image = \Illuminate\Support\Facades\DB::transaction(function () use ($albumId, $user, $filename, $extension, $file, $batchPrivacy, $s3Path, $batchTitle, $batchDescription, $totalFiles, $index) {
                    
                    // Logic for unique titles in batch
                    $finalTitle = $batchTitle ?: pathinfo($filename, PATHINFO_FILENAME);
                    if ($totalFiles > 1 && $batchTitle) {
                        $finalTitle .= " (" . ($index + 1) . ")";
                    }

                    $image = \App\Models\Image::create([
                        'album_id'    => $albumId,
                        'user_id'     => $user->id,
                        'title'       => $finalTitle,
                        'description' => $batchDescription,
                        'filename'    => $filename,
                        'file_type'   => $extension,
                        'size'        => $file->getSize(),
                        'privacy'     => $batchPrivacy,
                    ]);

                    $image->storage()->updateOrCreate(['image_id' => $image->id], [
                        'original_path' => $s3Path,
                        'path'          => $s3Path,
                        'imagekit_file_path' => $s3Path,
                        'md5_hash'      => md5_file($file->getRealPath()),
                    ]);

                    $image->moderation()->updateOrCreate(['image_id' => $image->id], [
                        'status'      => 'pending',
                        'is_sensitive' => false,
                        'is_visible'   => true,
                    ]);

                    return $image;
                });

                // ── Dispatch background moderation job ──
                \App\Jobs\ProcessImageModeration::dispatch($image->id, $jobId, $s3Path);

                $uploadedImages[] = [
                    'id'       => $image->id,
                    'filename' => $filename,
                    'title'    => $image->title,
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($s3Path);
                \Illuminate\Support\Facades\Log::error("Batch upload failed inside DB: " . $e->getMessage());
                continue;
            }
        }

        // Update status to "processing" (files are uploaded, moderation is running)
        try {
            $data = json_decode(Redis::get($redisKey), true);
            if ($data) {
                $data['status'] = 'processing';
                Redis::set($redisKey, json_encode($data), 'EX', 86400);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Redis failure in uploadBatch status update: " . $e->getMessage());
        }

        // ── Immediate 200 Response (Fast Response Pattern) ──
        return response()->json([
            'message' => __('messages.images_uploaded_analyzing'),
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

        // 🛡️ SECURITY: Only owner/collaborators can see moderation status
        $this->authorize('view', $album);

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
