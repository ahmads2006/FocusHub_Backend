<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageController extends Controller
{
    protected $imageService;

    public function __construct(ImageUploadService $imageService)
    {
        $this->imageService = $imageService;
    }

    public function index(Request $request)
    {
        // Public images are visible to everyone
        // Private images only to owners
        $query = Image::query();

        if ($request->has('album_id')) {
            $query->where('album_id', $request->album_id);
        }

        if (Auth::check()) {
            $query->where(function ($q) {
                $q->where('privacy', 'public')
                  ->orWhere('user_id', Auth::id());
            });
        } else {
            $query->where('privacy', 'public');
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:10240',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_comparison' => 'boolean'
        ]);

        $image = $this->imageService->upload(
            $request->file('image'), 
            $request->all(), 
            Auth::id()
        );

        return response()->json([
            'message' => 'Image uploaded successfully.',
            'data' => $image,
            'url' => $image->url
        ], 201);
    }

    public function show(Image $image)
    {
        if ($image->privacy !== 'public' && Auth::id() !== $image->user_id) {
            abort(403, 'Unauthorized.');
        }

        return response()->json($image);
    }

    public function update(Request $request, Image $image)
    {
        if (Auth::id() !== $image->user_id) {
            abort(403, 'Unauthorized.');
        }
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'privacy' => 'in:public,private',
            'album_id' => 'nullable|exists:albums,id',
            'is_comparison' => 'boolean'
        ]);

        $image->update($validated);

        return response()->json(['message' => 'Image updated successfully.', 'data' => $image]);
    }

    public function destroy(Image $image)
    {
        if (Auth::id() !== $image->user_id) {
            abort(403, 'Unauthorized.');
        }
        
        $this->imageService->delete($image);

        return response()->json(['message' => 'Image deleted successfully.']);
    }
}
