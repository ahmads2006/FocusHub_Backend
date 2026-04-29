<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class AlbumUploadController extends Controller
{
    public function uploadBatch(Request $request)
    {
        // Extremely flexible validation for debugging
        $request->validate([
            'album_id'    => 'nullable',
            'album_name'  => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy'     => 'nullable|in:public,private',
        ]);

        // Try to find files in any possible key (Files or Regular Inputs)
        $rawFiles = $request->file('images') ?: $request->file('images.0') ?: $request->input('images') ?: $request->input('images.0');
        
        \Illuminate\Support\Facades\Log::info('DEEP DEBUG UPLOAD:', [
            'input_images_type' => gettype($request->input('images')),
            'first_element_type' => gettype($request->input('images.0')),
            'first_element_sample' => substr(json_encode($request->input('images.0')), 0, 200),
            'all_input' => $request->except(['images']),
        ]);

        if (!$rawFiles && $request->hasFile('images.0')) {
             $rawFiles = $request->file('images.0');
        }

        // Normalize to array and filter nulls
        $files = is_array($rawFiles) ? array_filter($rawFiles) : ($rawFiles ? [$rawFiles] : []);

        if (empty($files)) {
            return response()->json([
                'message' => 'Still no images. Type: ' . gettype($request->input('images')) . ' | Sample: ' . substr(json_encode($request->input('images.0')), 0, 50),
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
                // Check for existing "Quick Uploads" or "General Uploads" to avoid duplicates
                $album = Album::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->whereIn('title', ['Quick Uploads', 'General Uploads'])
                    ->first();

                if (!$album) {
                    $album = Album::create([
                        'user_id' => $user->id,
                        'title'   => 'Quick Uploads',
                        'slug'      => 'quick-uploads-' . $user->id . '-' . Str::random(5),
                        'privacy'   => 'hidden',
                    ]);
                }
            }

            $uploadedPhotos = [];

            foreach ($files as $file) {
                if (!$file instanceof \Illuminate\Http\UploadedFile) {
                    continue;
                }

                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                $path = "albums/{$album->id}/{$filename}";

                Storage::disk('public')->put($path, file_get_contents($file));

                $photo = Photo::create([
                    'user_id'      => $user->id,
                    'album_id'     => $album->id,
                    'title'        => $file->getClientOriginalName(),
                    'file_path'    => $path,
                    'storage_disk' => 'public',
                    'file_size'    => $file->getSize(),
                    'mime_type'    => $file->getMimeType(),
                ]);

                $uploadedPhotos[] = $photo;
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully uploaded ' . count($uploadedPhotos) . ' photos',
                'album'   => $album,
                'photos'  => $uploadedPhotos,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch upload failed: ' . $e->getMessage());
            return response()->json(['message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }
}
