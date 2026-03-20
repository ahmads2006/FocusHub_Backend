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
        // 🚀 SMART ROUTING (v4.0): Use Cloud CDN (ImageKit) whenever possible for speed.
        // Direct CDN URLs bypass PHP entirely, which is much faster.
        $isInCloud = !empty($image->storage->imagekit_file_id);

        // We only force secure PHP-signed routes if:
        // 1. The image is NOT in the cloud (fallback storage like S3/local).
        // 2. The image is Rejected or Sensitive (requires blurring/blocking logic in PHP).
        // 3. The image is Private/Hidden (requires auth checks in PHP).
        $requiresSecureRoute = !$isInCloud ||
                               $image->isRejected() ||
                               $image->isPendingReview() ||
                               $image->getIsSensitiveAttribute() ||
                               $image->privacy !== 'public' ||
                               ($image->album && $image->album->privacy !== 'public');

        if ($requiresSecureRoute) {
            if ($this->canAccessOriginal($image) || $image->privacy === 'public') {
                // For gallery/thumbnail display → use inline preview route (renders in <img> tags)
                // For download contexts → use original route (forces download)
                if (in_array($context, ['gallery', 'preview', 'thumbnail', 'avatar', 'icon', 'square'])) {
                    return $this->generateSecurePreviewUrl($image);
                }
                if (in_array($context, ['original', 'source'])) {
                    return $this->generateSecureOriginalUrl($image);
                }
            }
        }

        // Use ImageKit CDN for everything else (Public images in cloud)
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
     * Generate a masked, temporary secure URL for the original high-res file (download).
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
     * Generate a masked, temporary secure URL for inline preview display in <img> tags.
     * Uses the assets.preview route which serves with Content-Disposition: inline.
     */
    protected function generateSecurePreviewUrl(Image $image): string
    {
        return URL::temporarySignedRoute(
            'assets.preview',
            now()->addMinutes(30),  // Longer TTL for gallery caching
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
