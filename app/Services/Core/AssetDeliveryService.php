<?php

namespace App\Services\Core;

use App\Models\Image;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;

class AssetDeliveryService
{
    /**
     * Get the appropriate URL for an image based on the requested context.
     *
     * @param Image $image
     * @param string $context 'gallery', 'thumbnail', 'avatar', 'original'
     * @return string
     */
    public function getUrl(Image $image, string $context = 'gallery'): string
    {
        // For private albums, if authorized, always prioritize the clean original/unblurred view
        if ($image->album && $image->album->privacy !== 'public') {
            if ($this->canAccessOriginal($image) && in_array($context, ['gallery', 'preview', 'original', 'source'])) {
                return $this->generateSecureOriginalUrl($image);
            }
        }

        switch ($context) {
            case 'avatar':
            case 'icon':
                return $image->getThumbnailUrl('avatar');

            case 'thumbnail':
            case 'square':
                return $image->getThumbnailUrl('square');

            case 'gallery':
            case 'preview':
                return $image->getThumbnailUrl('medium');

            case 'original':
            case 'source':
                return $this->generateSecureOriginalUrl($image);

            default:
                return $image->getThumbnailUrl('medium');
        }
    }

    /**
     * Generate a masked, temporary secure URL for the original high-res file.
     */
    protected function generateSecureOriginalUrl(Image $image): string
    {
        // Cache-busting via updated_at for fresh restoration/transition results
        return URL::temporarySignedRoute(
            'assets.original',
            now()->addMinutes(10),
            ['image' => $image->id, 'v' => $image->updated_at->timestamp]
        );
    }

    /**
     * Check if the current user/visitor has permission to access the original file.
     */
    public function canAccessOriginal(Image $image, $user = null): bool
    {
        $user = $user ?? Auth::user();

        // 1. Owner always has access
        if ($user && $user->id === $image->user_id) {
            return true;
        }

        // 2. Collaborators access
        if ($user && $image->album && $image->album->is_collaborative) {
            if ($image->album->collaborators()->where('users.id', $user->id)->exists()) {
                return true;
            }
        }

        // 3. Check if a valid shared link session exists for this image
        $sessionAccess = session("shared_link_access_{$image->id}");
        if ($sessionAccess !== null) {
            return $sessionAccess === 'download';
        }

        // 4. Public assets are downloadable if allowed by owner
        if ($image->privacy === 'public' && $image->allow_download) {
            return true;
        }

        return false;
    }
}
