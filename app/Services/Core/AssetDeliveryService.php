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
        // 🚀 SMART ROUTING (v4.1): Support S3 Origins.
        // An asset is "In Cloud" if it has an imagekit_file_id (legacy) OR an imagekit_file_path (S3/Origin).
        $isInCloud = !empty($image->storage?->imagekit_file_id) || !empty($image->storage?->imagekit_file_path);

        if (app()->environment('local') || !$isInCloud || $image->privacy !== 'public' || $image->isRejected() || ($image->album && $image->album->privacy !== 'public')) {
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

        // Using ImageKit directly whenever it's available.
        $imageKit = app(\App\Services\Core\ImageKitService::class);
        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;

        // Path Normalization: Strip redundant folder prefix returned by some ImageKit uploads
        // to prevent doubling it up in the final URL (ik.imagekit.io/vault/vault/...)
        if ($path) {
            $path = ltrim($path, '/');
            $folderPrefix = 'opticvault/';
            if (str_starts_with(strtolower($path), $folderPrefix)) {
                $path = substr($path, strlen($folderPrefix));
            }
        }

        switch ($context) {
            case 'avatar':
            case 'icon':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 150, 150) : $image->getThumbnailUrl('avatar');

            case 'thumbnail':
            case 'square':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 400, 400) : $image->getThumbnailUrl('square');

            case 'card':
                // Optimized 4:3 aspect ratio for gallery cards
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 400, 300) : $image->getThumbnailUrl('medium');

            case 'list':
                // Small version for list items/miniatures
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 200, 150) : $image->getThumbnailUrl('avatar');

            case 'gallery':
            case 'preview':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 800) : $image->getThumbnailUrl('medium');

            case 'placeholder':
                return $isInCloud ? $imageKit->getOptimizedUrl($path, 20, 20) : $image->getThumbnailUrl('avatar');

            case 'original':
            case 'source':
                return $this->generateSecureOriginalUrl($image);

            case 'gallery_watermarked':
                $transformations = [['width' => 800]];
                // إذا لم يكن المستخدم هو المالك، أضف علامة مائية
                if (!Auth::check() || Auth::id() !== $image->user_id) {
                    $transformations[] = [
                        'overlayImage' => 'logo.png', // المسار في ImageKit
                        'overlayFocus' => 'bottom_right',
                        'overlayAlpha' => '40', // شفافية العلامة المائية
                        'overlayWidth' => '150',
                    ];
                }
                return $isInCloud ? $imageKit->getEnhancedUrl($path, $transformations) : $image->getThumbnailUrl('medium');

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
