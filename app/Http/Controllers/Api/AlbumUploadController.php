<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Services\Core\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AlbumUploadController extends Controller
{
    protected ImageService $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function uploadBatch(Request $request)
    {
        $request->validate([
            'album_id'    => 'nullable',
            'album_name'  => 'nullable|string|max:255',
            'title'       => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy'     => 'nullable|in:public,private',
        ]);

        // Try to find files in any possible key
        $rawFiles = $request->file('images') ?: $request->file('images.0');

        if (!$rawFiles && $request->hasFile('images.0')) {
            $rawFiles = $request->file('images.0');
        }

        // Normalize to array and filter nulls
        $files = is_array($rawFiles) ? array_filter($rawFiles) : ($rawFiles ? [$rawFiles] : []);

        if (empty($files)) {
            return response()->json([
                'message' => 'No images received.',
            ], 422);
        }

        $user = $request->user();
        $album = null;

        try {
            // 1. Determine the Album
            if ($request->album_id) {
                $album = Album::where('user_id', $user->id)->find($request->album_id);
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
                        'is_public'   => ($request->privacy === 'public'),
                    ]);
                }
            }

            if (!$album) {
                $album = Album::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->whereIn('title', ['Quick Uploads', 'General Uploads'])
                    ->first();

                if (!$album) {
                    $album = Album::create([
                        'user_id'   => $user->id,
                        'title'     => 'Quick Uploads',
                        'slug'      => 'quick-uploads-' . $user->id . '-' . Str::random(5),
                        'privacy'   => 'hidden',
                    ]);
                }
            }

            // 2. Process each file through the full ImageService pipeline (S3 + DB + AI)
            $uploadedImages = [];

            foreach ($files as $file) {
                if (!$file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $data = [
                    'album_id'    => $album->id,
                    'title'       => $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                    'description' => $request->description,
                    'privacy'     => $request->privacy ?: 'public',
                ];

                if ($request->has('allow_download')) {
                    $data['allow_download'] = true;
                }

                $image = $this->imageService->processAndUpload($file, $data, $user->id);
                $uploadedImages[] = $image;
            }

            return response()->json([
                'message' => 'Successfully uploaded ' . count($uploadedImages) . ' image(s)',
                'album'   => $album,
                'images'  => $uploadedImages,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Batch upload failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
