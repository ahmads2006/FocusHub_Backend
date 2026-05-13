<?php

namespace App\Policies;

use App\Models\Album;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AlbumPolicy
{
    /**
     * Determine whether the user can view the album.
     */
    public function view(?User $user, Album $album): bool
    {
        // Public albums are visible to everyone
        if ($album->privacy === 'public') {
            return true;
        }

        // Check if user has temporary session access via shared link
        if (session()->has("shared_link_access_album_{$album->id}")) {
            return true;
        }

        // Hidden albums are only accessible via shared link (handled above by session)
        // or by owner/collaborators
        if ($album->privacy === 'hidden') {
            if ($user && ($user->id === $album->user_id || $album->collaborators->contains($user))) {
                return true;
            }
            return false;
        }

        // Private albums are only for owner and collaborators
        if ($album->privacy === 'private') {
            return $user && ($user->id === $album->user_id || $album->collaborators->contains($user));
        }

        return false;
    }

    /**
     * Determine whether the user can update the album.
     */
    public function update(User $user, Album $album): Response
    {
        // Only owner can update (Rename, Privacy, etc.)
        return $user->id === $album->user_id
            ? Response::allow()
            : Response::deny('Only the album owner can update settings.');
    }

    /**
     * Determine whether the user can upload photos to the album.
     */
    public function uploadPhoto(User $user, Album $album): Response
    {
        if ($user->id === $album->user_id) {
            return Response::allow();
        }

        $collaborator = $album->collaborators()->where('user_id', $user->id)->first();
        if ($collaborator && in_array($collaborator->pivot->role, ['admin', 'contributor'])) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to upload photos to this album.');
    }

    /**
     * Determine whether the user can delete the album.
     */
    public function delete(User $user, Album $album): Response
    {
        // Only owner can delete
        return $user->id === $album->user_id
            ? Response::allow()
            : Response::deny('Only the album owner can delete this album.');
    }

    /**
     * Determine whether the user can share the album.
     */
    public function share(User $user, Album $album): Response
    {
        // Owner can always share
        if ($user->id === $album->user_id) {
            return Response::allow();
        }

        // Admins and Contributors can share
        $collaborator = $album->collaborators()->where('user_id', $user->id)->first();
        if ($collaborator && in_array($collaborator->pivot->role, ['admin', 'contributor'])) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to generate share links for this album.');
    }
}
