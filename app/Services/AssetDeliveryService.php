<?php

namespace App\Services;

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
        // We use a signed URL that expires in 5 minutes
        return URL::temporarySignedRoute(
            'assets.original',
            now()->addMinutes(5),
            ['image' => $image->id]
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

        // 2. Check if a valid shared link session exists with download permissions
        // This would integrate with the SharedLink session logic
        if (session("shared_link_access_{$image->id}") === 'download') {
            return true;
        }

        return false;
    }
}
