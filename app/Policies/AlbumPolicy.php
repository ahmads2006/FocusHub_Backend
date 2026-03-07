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

        // Hidden albums are only accessible via shared link (handled in controller/middleware)
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
    public function update(User $user, Album $album): bool
    {
        // Owner always can
        if ($user->id === $album->user_id) {
            return true;
        }

        // Admin collaborators can update
        $collaborator = $album->collaborators()->where('user_id', $user->id)->first();
        return $collaborator && $collaborator->pivot->role === 'admin';
    }

    /**
     * Determine whether the user can upload photos to the album.
     */
    public function uploadPhoto(User $user, Album $album): bool
    {
        if ($user->id === $album->user_id) {
            return true;
        }

        $collaborator = $album->collaborators()->where('user_id', $user->id)->first();
        return $collaborator && in_array($collaborator->pivot->role, ['admin', 'contributor']);
    }

    /**
     * Determine whether the user can delete the album.
     */
    public function delete(User $user, Album $album): bool
    {
        // Only owner can delete
        return $user->id === $album->user_id;
    }
}
