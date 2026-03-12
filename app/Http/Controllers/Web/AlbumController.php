<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use App\Models\Album;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AlbumController extends Controller
{
    /**
     * Display the specified album and its collaborators.
     */
    public function show(Album $album)
    {
        // Authorization check: User must be owner or collaborator to see private/hidden albums
        if ($album->privacy !== 'public' && 
            Auth::id() !== $album->user_id && 
            !$album->collaborators->contains(Auth::id())) {
            abort(403, 'غير مصرح لك بالوصول لهذا الألبوم.');
        }

        $album->load(['images.user', 'collaborators']);
        
        return view('albums.show', compact('album'));
    }

    /**
     * Add a collaborator to the album.
     */
    public function addCollaborator(Request $request, Album $album)
    {
        // Only owner can add collaborators
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,contributor,viewer',
        ]);

        $userToAdd = User::where('email', $validated['email'])->first();

        if ($userToAdd->id === $album->user_id) {
            return back()->withErrors(['email' => 'لا يمكنك إضافة نفسك كمتعاون (أنت مالك الألبوم).']);
        }

        if ($album->collaborators->contains($userToAdd->id)) {
            return back()->withErrors(['email' => 'هذا المستخدم متعاون بالفعل في هذا الألبوم.']);
        }

        $album->collaborators()->attach($userToAdd->id, ['role' => $validated['role']]);

        return back()->with('success', "تم إضافة {$userToAdd->name} كمتعاون بنجاح.");
    }

    /**
     * Remove a collaborator from the album.
     */
    public function removeCollaborator(Album $album, User $user)
    {
        // Only owner can remove collaborators
        if (Auth::id() !== $album->user_id) {
            abort(403);
        }

        $album->collaborators()->detach($user->id);

        return back()->with('success', "تم إزالة المتعاون {$user->name} بنجاح.");
    }
}
