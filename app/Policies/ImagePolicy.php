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
            $linkId = session("shared_link_id_{$image->id}");
            if ($linkId) {
                $link = \App\Models\SharedLink::find($linkId);
                if ($link && !$link->is_active) {
                    session()->forget([
                        "shared_link_access_{$image->id}",
                        "shared_link_watermark_{$image->id}",
                        "shared_link_id_{$image->id}"
                    ]);
                    return false;
                }
            }
            return true;
        }

        // If the image belongs to an album, check if the visitor is authorized to view that album
        if ($image->album) {
            $album = $image->album;
            // 1. Public albums are visible to everyone
            if ($album->privacy === 'public') {
                return true;
            }
            // 2. Shared link access to the album
            if (session()->has("shared_link_access_album_{$album->id}")) {
                $linkId = session("shared_link_id_album_{$album->id}");
                if ($linkId) {
                    $link = \App\Models\SharedLink::find($linkId);
                    if ($link && !$link->is_active) {
                        session()->forget([
                            "shared_link_access_album_{$album->id}",
                            "shared_link_id_album_{$album->id}"
                        ]);
                        return false;
                    }
                }
                return true;
            }
            // 3. Album owner or collaborators
            if ($user && ($user->id === $album->user_id || $album->collaborators()->where('user_id', $user->id)->exists())) {
                return true;
            }
        }

        if (!$user) {
            return false;
        }

        // Owner can always view
        if ($user->id === $image->user_id) {
            return true;
        }

        // Album owner can view
        if ($image->album && $user->id === $image->album->user_id) {
            return true;
        }

        // Collaborators can view
        if ($image->album && $image->album->collaborators()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    public function download(?User $user, Image $image): \Illuminate\Auth\Access\Response
    {
        // Owner can always download
        if ($user && $user->id === $image->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // 🛡️ Enforcement: If the owner disabled downloading, nobody else can download (even admins)
        // Check general setting: allow_download
        $canGlobalDownload = (bool)($image->settings?->allow_download ?? false);
        
        if (!$canGlobalDownload) {
             return \Illuminate\Auth\Access\Response::deny('Download is restricted for this image by the owner.');
        }

        // If the request has an active shared link session, it MUST dictate the permission.
        $hasImageSharedLink = session()->has("shared_link_access_{$image->id}");
        $hasAlbumSharedLink = $image->album_id && session()->has("shared_link_access_album_{$image->album_id}");

        if ($hasImageSharedLink || $hasAlbumSharedLink) {
            if ($hasImageSharedLink) {
                $linkId = session("shared_link_id_{$image->id}");
                if ($linkId) {
                    $link = \App\Models\SharedLink::find($linkId);
                    if ($link && !$link->is_active) {
                        session()->forget([
                            "shared_link_access_{$image->id}",
                            "shared_link_watermark_{$image->id}",
                            "shared_link_id_{$image->id}"
                        ]);
                        return \Illuminate\Auth\Access\Response::deny('Shared link is no longer active.');
                    }
                }
                
                if (session("shared_link_access_{$image->id}") === 'download') {
                    return \Illuminate\Auth\Access\Response::allow();
                } else {
                    return \Illuminate\Auth\Access\Response::deny('Download is restricted for this shared link.');
                }
            }

            if ($hasAlbumSharedLink) {
                $linkId = session("shared_link_id_album_{$image->album_id}");
                if ($linkId) {
                    $link = \App\Models\SharedLink::find($linkId);
                    if ($link && !$link->is_active) {
                        session()->forget([
                            "shared_link_access_album_{$image->album_id}",
                            "shared_link_id_album_{$image->album_id}"
                        ]);
                        return \Illuminate\Auth\Access\Response::deny('Shared album link is no longer active.');
                    }
                }

                if (session("shared_link_access_album_{$image->album_id}") === 'download') {
                    return \Illuminate\Auth\Access\Response::allow();
                } else {
                    return \Illuminate\Auth\Access\Response::deny('Download is restricted for this shared album link.');
                }
            }
        }

        // Check for album access download permission
        if ($image->album) {
            $album = $image->album;
            $hasAlbumAccess = false;
            if ($album->privacy === 'public') {
                $hasAlbumAccess = true;
            } elseif (session()->has("shared_link_access_album_{$album->id}")) {
                $hasAlbumAccess = true;
            } elseif ($user && ($user->id === $album->user_id || $album->collaborators()->where('user_id', $user->id)->exists())) {
                $hasAlbumAccess = true;
            }

            if ($hasAlbumAccess) {
                return \Illuminate\Auth\Access\Response::allow();
            }
        }

        // Public download is allowed only if the owner enabled it in general settings
        if ($image->privacy === 'public') {
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

    /**
     * Determine whether the user can share the image.
     */
    public function share(User $user, Image $image): \Illuminate\Auth\Access\Response
    {
        // Anyone can share public images
        if ($image->privacy === 'public') {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Owner of the image can always share
        if ($user->id === $image->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Owner of the album can share any image in it
        if ($image->album && $user->id === $image->album->user_id) {
            return \Illuminate\Auth\Access\Response::allow();
        }

        // Admin/Contributor collaborators of the album can share any image in it
        if ($image->album) {
            $collaborator = $image->album->collaborators()->where('user_id', $user->id)->first();
            if ($collaborator && in_array($collaborator->pivot->role, ['admin', 'contributor'])) {
                return \Illuminate\Auth\Access\Response::allow();
            }
        }

        return \Illuminate\Auth\Access\Response::deny('You do not have permission to generate share links for this image.');
    }
}
