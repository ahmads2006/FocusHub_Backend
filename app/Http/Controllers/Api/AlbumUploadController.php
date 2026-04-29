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
    public function batchUpload(Request $request)
    {
        $request->validate([
            'images'      => 'required|array',
            'images.*'    => 'image|mimes:jpeg,png,jpg,gif,webp|max:20480', // 20MB max
            'album_id'    => 'nullable|exists:albums,id',
            'album_name'  => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy'     => 'nullable|in:public,private',
        ]);

        $user = $request->user();
        $album = null;

        try {
            DB::beginTransaction();

            // 1. Determine the Album
            if ($request->album_id) {
                $album = Album::where('user_id', $user->id)->findOrFail($request->album_id);
            } elseif ($request->album_name) {
                $album = Album::firstOrCreate([
                    'user_id' => $user->id,
                    'title'   => $request->album_name,
                ], [
                    'slug'        => Str::slug($request->album_name) . '-' . Str::random(5),
                    'description' => $request->description,
                    'is_public'   => ($request->privacy === 'public'),
                ]);
            } else {
                // Default "Quick Uploads" album
                $album = Album::firstOrCreate([
                    'user_id' => $user->id,
                    'title'   => 'Quick Uploads',
                ], [
                    'slug'      => 'quick-uploads-' . $user->id,
                    'is_public' => true,
                ]);
            }

            $uploadedPhotos = [];
            $files = $request->file('images');

            foreach ($files as $file) {
                // 2. Generate Paths
                $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
                $path = "albums/{$album->id}/{$filename}";

                // 3. Process & Upload Image (Using original for now, could add optimization here)
                // In a real high-end app, we'd generate thumbnails here.
                Storage::disk('public')->put($path, file_get_contents($file));

                // 4. Create Database Record
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
