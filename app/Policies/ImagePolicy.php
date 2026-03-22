<?php

namespace App\Policies;

use App\Models\Image;
use App\Models\User;

class ImagePolicy
{
    public function view(?User $user, Image $image): bool
    {
        if ($image->privacy === 'public') {
            return true;
        }

        if (session()->has("shared_link_access_{$image->id}")) {
            return true;
        }

        return $user && $user->id === $image->user_id;
    }

    public function download(?User $user, Image $image): \Illuminate\Auth\Access\Response
    {
        // Owner can always download
        if ($user && $user->id === $image->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Check for shared link temporary session access with download permission
        if (session("shared_link_access_{$image->id}") === 'download') {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Public download is allowed only if the owner enabled it general settings
        if ($image->privacy === 'public' && $image->allow_download) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        return \Illuminate\Auth\Access\Response::deny('Download is restricted for this image.');
    }

    public function update(User $user, Image $image): \Illuminate\Auth\Access\Response
    {
        // Owner of the image can update
        if ($user->id === $image->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Owner of the album can update any image in it
        if ($image->album && $user->id === $image->album->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Admin collaborator of the album can update any image in it
        if ($image->album) {
            $collaborator = $image->album->collaborators()->where('user_id', $user->id)->first();
            if ($collaborator && $collaborator->pivot->role === 'admin') {
                return \Illuminate\Auth\Access\Response::allow();
            }
        }

        return \Illuminate\Auth\Access\Response::deny('You do not have permission to update this image.');
    }

    public function delete(User $user, Image $image): \Illuminate\Auth\Access\Response
    {
        // Owner of the image can delete
        if ($user->id === $image->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Owner of the album can delete any image in it
        if ($image->album && $user->id === $image->album->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Admin collaborator of the album can delete any image in it
        if ($image->album) {
            $collaborator = $image->album->collaborators()->where('user_id', $user->id)->first();
            if ($collaborator && $collaborator->pivot->role === 'admin') {
                return \Illuminate\Auth\Access\Response::allow();
            }
        }

        return \Illuminate\Auth\Access\Response::deny('You do not have permission to delete this image.');
    }
}
