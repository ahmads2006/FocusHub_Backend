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
        // 🚀 SMART ROUTING (v4.0): Force secure signed routes for anything non-public, rejected, or NOT in ImageKit.
        // This ensures cloud-fallback assets (S3/local) are found correctly.
        $isInCloud = !empty($image->storage?->imagekit_file_id);
        if (!$isInCloud || $image->privacy !== 'public' || $image->isRejected() || ($image->album && $image->album->privacy !== 'public')) {
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

        // If it's public and safe and in the cloud, serve directly from the CDN
        $imageKit = app(\App\Services\Core\ImageKitService::class);
        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;

        switch ($context) {
            case 'avatar':
            case 'icon':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 150, 150) : $image->getThumbnailUrl('avatar');

            case 'thumbnail':
            case 'square':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 400, 400) : $image->getThumbnailUrl('square');

            case 'gallery':
            case 'preview':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 800) : $image->getThumbnailUrl('medium');

            case 'original':
            case 'source':
                return $this->generateSecureOriginalUrl($image);

            default:
                return $isInCloud ? $imageKit->getOptimizedUrl($path) : $image->getThumbnailUrl('medium');
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
