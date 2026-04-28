<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\Core\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index(Request $request)
    {
        $query = Image::query();

        if ($request->has('album_id')) {
            $album = \App\Models\Album::findOrFail($request->album_id);
            
            // Check if user has access to this album
            $this->authorize('view', $album);
            
            $query->where('album_id', $album->id);
        } else {
            // Global feed: only public images or user's own images
            $query->where(function ($q) {
                $q->where('privacy', 'public')
                  ->orWhere('user_id', Auth::id());
            });
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|mimes:jpeg,png,jpg,webp,gif,heic,heif,tiff,tif,bmp,svg,jfif,pjpeg,pjp|max:25600',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_comparison' => 'boolean'
        ]);

        $user = Auth::user();
        
        if ($request->album_id) {
            $album = \App\Models\Album::findOrFail($request->album_id);
            $this->authorize('uploadPhoto', $album);
        }

        $file = $request->file('image');

        // ── Storage Quota Check (5GB Drive System) ──
        if (!$user->hasEnoughStorage($file->getSize())) {
            return response()->json([
                'message' => __('messages.storage_limit_exceeded')
            ], 403);
        }

        $image = $this->imageService->processAndUpload(
            $file, 
            $request->all(), 
            $user->id
        );

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'data' => $image,
            'url' => $image->url
        ], 201);
    }

    public function show(Image $image)
    {
        $this->authorize('view', $image);
        return response()->json($image);
    }

    public function update(Request $request, Image $image)
    {
        $this->authorize('update', $image);
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'is_comparison' => 'boolean'
        ]);

        $image->update($validated);

        return response()->json([
            'message' => 'Image updated successfully.',
            'data' => $image
        ]);
    }

    public function destroy(Image $image)
    {
        $this->authorize('delete', $image);
        
        $this->imageService->delete($image);

        return response()->json([
            'message' => 'Image deleted successfully.'
        ]);
    }

    /**
     * Re-trigger AI analysis (Safety + Tagging) for a specific image.
     */
    public function retryScan(Image $image)
    {
        $this->authorize('update', $image);

        // Dispatch the AI analysis job to the queue
        \App\Jobs\AnalyzeImageLabelsJob::dispatch($image);

        return response()->json([
            'success' => true,
            'message' => __('messages.reanalyze_sent'),
        ]);
    }
}
