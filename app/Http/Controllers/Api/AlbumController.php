<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlbumController extends Controller
{
    public function index()
    {
        $albums = Album::where('privacy', 'public')
            ->orWhere('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($albums);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'privacy' => 'nullable|in:public,private,hidden',
            'is_collaborative' => 'boolean',
        ]);

        $album = Auth::user()->ownedAlbums()->create($validated);

        return response()->json($album, 201);
    }

    public function show(Album $album)
    {
        if ($album->privacy !== 'public' && Auth::id() !== $album->user_id && !$album->collaborators->contains(Auth::id())) {
            abort(403);
        }

        return response()->json($album->load('images'));
    }

    public function update(Request $request, Album $album)
    {
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'privacy' => 'nullable|in:public,private,hidden',
            'is_collaborative' => 'boolean',
        ]);

        $album->update($validated);

        return response()->json($album);
    }

    public function destroy(Album $album)
    {
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $album->delete();

        return response()->json(['message' => 'Album deleted successfully.']);
    }
}
