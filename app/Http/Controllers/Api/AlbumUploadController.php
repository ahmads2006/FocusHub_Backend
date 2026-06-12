<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Services\Core\ImageService;
use App\Jobs\ExtractArchiveJob;
use App\Jobs\ModerateImageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlbumUploadController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Synchronous batch upload (legacy).
     */
    public function uploadBatch(Request $request)
    {
        $request->validate([
            'album_id'    => 'nullable|uuid|exists:albums,id',
            'album_name'  => 'nullable|string|max:255',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy'     => 'nullable|in:public,private',
            'is_cover'    => 'nullable',
        ]);

        $rawFiles = $request->file('images') ?: $request->file('images.0');

        if (!$rawFiles && $request->hasFile('images.0')) {
            $rawFiles = $request->file('images.0');
        }

        $files = is_array($rawFiles) ? array_filter($rawFiles) : ($rawFiles ? [$rawFiles] : []);

        if (empty($files)) {
            Log::warning('Upload attempt with no files.', ['request' => $request->all()]);
            return response()->json([
                'message' => 'No images received.',
            ], 422);
        }

        foreach ($files as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                if (!$file->isValid()) {
                    Log::error('Invalid file upload detected.', [
                        'error' => $file->getErrorMessage(),
                        'client_name' => $file->getClientOriginalName()
                    ]);
                    return response()->json(['message' => 'File upload error: ' . $file->getErrorMessage()], 422);
                }
                
                if (!$file->getRealPath() || is_dir($file->getRealPath())) {
                    Log::error('File path is invalid or a directory.', [
                        'path' => $file->getRealPath(),
                        'client_name' => $file->getClientOriginalName()
                    ]);
                    return response()->json(['message' => 'Invalid file path received.'], 422);
                }
            }
        }

        $user = $request->user();
        $album = null;

        try {
            if ($request->album_id) {
                $album = Album::with('settings')->find($request->album_id);

                if (!$album) {
                    return response()->json([
                        'message'    => 'The selected album was not found.',
                        'error_code' => 'album_not_found',
                    ], 422);
                }

                $this->authorize('uploadPhoto', $album);
            }

            if (!$album && $request->album_name) {
                $normalizedTitle = trim($request->album_name);
                $album = Album::where('user_id', $user->id)
                    ->where('title', $normalizedTitle)
                    ->first();

                if (!$album) {
                    $album = Album::create([
                        'user_id'     => $user->id,
                        'title'       => $normalizedTitle,
                        'slug'        => Str::slug($normalizedTitle) . '-' . Str::random(5),
                        'description' => $request->description,
                        'privacy'     => $request->privacy ?: 'private',
                    ]);
                }
            }

            $photoPrivacy = $request->privacy ?: 'public';

            if ($album) {
                $albumPrivacy = $album->privacy ?? 'public';

                if ($photoPrivacy === 'private' && $albumPrivacy === 'public') {
                    return response()->json([
                        'message' => 'Cannot upload a private photo to a public album. Please change the photo privacy to public or choose a private album.',
                        'error_code' => 'privacy_conflict',
                    ], 422);
                }

                if ($photoPrivacy === 'public' && $albumPrivacy === 'private') {
                    return response()->json([
                        'message' => 'Cannot upload a public photo to a private album. Please change the photo privacy to private or choose a public album.',
                        'error_code' => 'privacy_conflict',
                    ], 422);
                }
            }

            $uploadedImages = [];

            foreach ($files as $file) {
                if (!$file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $data = [
                    'album_id'    => $album?->id,
                    'title'       => $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'description' => $request->description,
                    'privacy'     => $photoPrivacy,
                ];

                if ($request->has('allow_download')) {
                    $data['allow_download'] = filter_var($request->allow_download, FILTER_VALIDATE_BOOLEAN);
                }
                
                if ($request->has('watermark_on_download')) {
                    $data['watermark_on_download'] = filter_var($request->watermark_on_download, FILTER_VALIDATE_BOOLEAN);
                }

                $wmFields = ['watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
                foreach ($wmFields as $field) {
                    if ($request->has($field)) {
                        $data[$field] = $request->input($field);
                    }
                }
                
                Log::info("Extracted Watermark Data for Image", $data);

                $image = $this->imageService->processAndUpload($file, $data, $user->id);
                $uploadedImages[] = $image;

                if ($request->has('is_cover') && filter_var($request->is_cover, FILTER_VALIDATE_BOOLEAN) && $album) {
                    $album->update(['cover_image' => $image->url]);
                }
            }

            return response()->json([
                'message' => 'Successfully uploaded ' . count($uploadedImages) . ' image(s)',
                'album'   => $album,
                'images'  => $uploadedImages,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Batch upload failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Asynchronous album upload.
     */
    public function uploadAlbum(Request $request)
    {
        $request->validate([
            'album_id'              => 'nullable|uuid|exists:albums,id',
            'album_name'            => 'nullable|string|max:255',
            'title'                 => 'nullable|string|max:255',
            'description'           => 'nullable|string',
            'privacy'               => 'nullable|in:public,private',
            'allow_download'        => 'nullable',
            'watermark_on_download' => 'nullable',
            'watermark_font_size'   => 'nullable',
            'watermark_opacity'     => 'nullable',
            'watermark_color'       => 'nullable',
            'watermark_type'        => 'nullable',
            'watermark_text'        => 'nullable',
        ]);

        $rawFiles = $request->file('images') ?: $request->file('images.0');
        if (!$rawFiles && $request->hasFile('images.0')) {
            $rawFiles = $request->file('images.0');
        }
        $archiveFile = $request->file('archive');
        
        $files = is_array($rawFiles) ? array_filter($rawFiles) : ($rawFiles ? [$rawFiles] : []);
        
        $isArchive = false;
        $fileToExtract = null;

        if ($archiveFile) {
            $isArchive = true;
            $fileToExtract = $archiveFile;
        } elseif (count($files) === 1) {
            $firstFile = $files[0];
            $ext = strtolower($firstFile->getClientOriginalExtension());
            if (in_array($ext, ['zip', 'rar', '7z'])) {
                $isArchive = true;
                $fileToExtract = $firstFile;
            }
        }

        $user = $request->user();
        $album = null;

        try {
            if ($request->album_id) {
                $album = Album::with('settings')->find($request->album_id);
                if (!$album) {
                    return response()->json(['message' => 'The selected album was not found.'], 422);
                }
                $this->authorize('uploadPhoto', $album);
            }

            if (!$album && $request->album_name) {
                $normalizedTitle = trim($request->album_name);
                $album = Album::where('user_id', $user->id)
                    ->where('title', $normalizedTitle)
                    ->first();

                if (!$album) {
                    $album = Album::create([
                        'user_id'     => $user->id,
                        'title'       => $normalizedTitle,
                        'slug'        => Str::slug($normalizedTitle) . '-' . Str::random(5),
                        'description' => $request->description,
                        'privacy'     => $request->privacy ?: 'private',
                    ]);
                }
            }

            if (!$album) {
                return response()->json(['message' => 'Album is required.'], 422);
            }

            $photoPrivacy = $request->privacy ?: 'public';

            if ($album) {
                $albumPrivacy = $album->privacy ?? 'public';

                if ($photoPrivacy === 'private' && $albumPrivacy === 'public') {
                    return response()->json([
                        'message' => 'Cannot upload a private photo to a public album.',
                        'error_code' => 'privacy_conflict',
                    ], 422);
                }

                if ($photoPrivacy === 'public' && $albumPrivacy === 'private') {
                    return response()->json([
                        'message' => 'Cannot upload a public photo to a private album.',
                        'error_code' => 'privacy_conflict',
                    ], 422);
                }
            }

            $jobId = (string) Str::uuid();
            $redisKey = 'opticvault:upload_progress:' . $jobId;

            $metadata = [
                'privacy'               => $photoPrivacy,
                'description'           => $request->description,
                'allow_download'        => $request->has('allow_download') ? filter_var($request->allow_download, FILTER_VALIDATE_BOOLEAN) : true,
                'watermark_on_download' => $request->has('watermark_on_download') ? filter_var($request->watermark_on_download, FILTER_VALIDATE_BOOLEAN) : false,
            ];

            $wmFields = ['watermark_font_size', 'watermark_opacity', 'watermark_color', 'watermark_type', 'watermark_text'];
            foreach ($wmFields as $field) {
                if ($request->has($field)) {
                    $metadata[$field] = $request->input($field);
                }
            }

            if ($isArchive) {
                $ext = strtolower($fileToExtract->getClientOriginalExtension());
                $tempFileName = $jobId . '.' . $ext;
                $tempPath = 'quarantine/archives/' . $tempFileName;
                
                Storage::disk('local')->put($tempPath, file_get_contents($fileToExtract->getRealPath()));
                
                Redis::setex($redisKey, 86400, json_encode([
                    'total_items' => 0,
                    'processed_items' => 0,
                    'rejected_items' => 0,
                    'failed_items' => 0,
                    'status' => 'extracting',
                    'album_id' => $album->id
                ]));
                
                ExtractArchiveJob::dispatch($tempPath, $jobId, $album->id, $user->id, $metadata);
            } else {
                if (empty($files)) {
                    return response()->json(['message' => 'No images received.'], 422);
                }

                $quarantineDir = 'quarantine/extracted_' . $jobId;
                Storage::disk('local')->makeDirectory($quarantineDir);

                Redis::setex($redisKey, 86400, json_encode([
                    'total_items' => count($files),
                    'processed_items' => 0,
                    'rejected_items' => 0,
                    'failed_items' => 0,
                    'status' => 'processing',
                    'album_id' => $album->id
                ]));

                foreach ($files as $file) {
                    if (!$file instanceof \Illuminate\Http\UploadedFile) {
                        continue;
                    }
                    
                    $filename = Str::random(20) . '.' . $file->getClientOriginalExtension();
                    $tempPath = $quarantineDir . '/' . $filename;
                    
                    Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));
                    
                    $imgMetadata = array_merge($metadata, [
                        'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
                    ]);

                    ModerateImageJob::dispatch($tempPath, $jobId, $album->id, $user->id, $imgMetadata);
                }
            }

            return response()->json([
                'success' => true,
                'jobId'   => $jobId,
                'message' => 'Async upload started successfully.'
            ], 202);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\Exception $e) {
            Log::error('Async upload failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the progress of an asynchronous upload.
     */
    public function getUploadProgress(string $jobId)
    {
        $redisKey = 'opticvault:upload_progress:' . $jobId;
        $data = Redis::get($redisKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or expired.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'progress' => json_decode($data, true)
        ]);
    }

    /**
     * Get the status of an album (whether it has pending scans).
     */
    public function getAlbumStatus(Album $album)
    {
        $pendingCount = $album->images()
            ->whereHas('moderation', fn($q) => $q->whereIn('status', ['pending_review', 'under_review']))
            ->count();

        return response()->json([
            'success' => true,
            'pending_count' => $pendingCount,
            'status' => $pendingCount > 0 ? 'processing' : 'completed'
        ]);
    }
}
