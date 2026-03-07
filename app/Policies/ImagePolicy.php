<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;

class ImagePolicy
{
    public function view(?User $user, Image $image): bool
    {
        if ($image->visibility === 'public') {
            return true;
        }
        return $user && $user->id === $image->user_id;
    }

    public function update(User $user, Image $image): bool
    {
        return $user->id === $image->user_id;
    }

    public function delete(User $user, Image $image): bool
    {
        // Owner of the image can delete
        if ($user->id === $image->user_id) {
            return true;
        }

        // Owner of the album can delete any image in it
        if ($image->album && $user->id === $image->album->user_id) {
            return true;
        }

        // Admin collaborator of the album can delete any image in it
        if ($image->album) {
            $collaborator = $image->album->collaborators()->where('user_id', $user->id)->first();
            return $collaborator && $collaborator->pivot->role === 'admin';
        }

        return false;
    }
}
