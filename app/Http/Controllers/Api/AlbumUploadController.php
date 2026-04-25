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
            'album_id'   => 'nullable|exists:albums,id',
            'album_name' => 'nullable|string|max:255',
            'images'     => 'required|array|min:1|max:100',
            'images.*'   => 'required|image|mimes:jpeg,png,jpg,webp,gif,heic,heif,tiff,tif,bmp,svg|max:10240', // 10MB each
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
                    'message' => __('messages.upload_limit_batches')
                ], 429);
            }
        }

        $albumId = $request->album_id;

        if (!$albumId) {
            $album = \App\Models\Album::create([
                'user_id' => $user->id,
                'title'   => $request->album_name ?: 'Batch Upload ' . date('Y-m-d H:i'),
                'privacy' => 'public',
            ]);
            $albumId = $album->id;
        } else {
            // 🛡️ SECURITY: Verify user has upload permission to this album
            $album = \App\Models\Album::findOrFail($albumId);
            $this->authorize('uploadPhoto', $album);
        }

        $files = $request->file('images');
        $totalFiles = count($files);

        // ── Storage Quota Check (5GB Drive System) ──
        $totalBatchSize = array_reduce($files, fn($carry, $f) => $carry + $f->getSize(), 0);
        if (!$user->hasEnoughStorage($totalBatchSize)) {
            return response()->json([
                'message' => __('messages.storage_limit_batch')
            ], 403);
        }

        $jobId = Str::uuid()->toString();

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

            try {
                $image = \Illuminate\Support\Facades\DB::transaction(function () use ($albumId, $user, $filename, $extension, $file, $inheritedPrivacy, $s3Path, $jobId) {
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

                    return $image;
                });

                // ── Dispatch background moderation job to Redis queue ──
                \App\Jobs\ProcessImageModeration::dispatch($image->id, $jobId, $s3Path);

                $uploadedImages[] = [
                    'id'       => $image->id,
                    'filename' => $filename,
                ];
            } catch (\Exception $e) {
                // If DB fails, securely delete the orphaned S3 file
                \Illuminate\Support\Facades\Storage::disk('s3')->delete($s3Path);
                \Illuminate\Support\Facades\Log::error("Batch upload failed inside DB, wiped S3 file: " . $e->getMessage());
                // Skip adding to uploadedImages, continue with the next image in the batch
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
