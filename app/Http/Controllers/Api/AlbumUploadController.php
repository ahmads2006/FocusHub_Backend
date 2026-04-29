<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image as ImageModel;
use App\Models\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlbumUploadController extends Controller
{
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
            DB::beginTransaction();

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

            $uploadedImages = [];

            foreach ($files as $file) {
                if (!$file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                $path = "albums/{$album->id}/{$filename}";

                // Store the file
                Storage::disk('public')->put($path, file_get_contents($file));

                // Determine the title
                $imageTitle = $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                // Create Image record (matches the actual Image model fillable fields)
                $image = ImageModel::create([
                    'user_id'      => $user->id,
                    'album_id'     => $album->id,
                    'title'        => $imageTitle,
                    'description'  => $request->description,
                    'filename'     => $filename,
                    'size'         => $file->getSize(),
                    'privacy'      => $request->privacy ?: 'public',
                ]);

                // Create ImageStorage record for file path tracking
                ImageStorage::create([
                    'image_id'      => $image->id,
                    'path'          => $path,
                    'original_path' => $path,
                ]);

                $uploadedImages[] = $image;
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully uploaded ' . count($uploadedImages) . ' image(s)',
                'album'   => $album,
                'images'  => $uploadedImages,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch upload failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
