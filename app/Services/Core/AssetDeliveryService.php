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
        // 🚀 SMART ROUTING (v5.1): Maximum Privacy for Private Assets.
        $isInCloud = !empty($image->storage?->imagekit_file_id) || !empty($image->storage?->imagekit_file_path);
        $isPublic = $image->privacy === 'public' && (!$image->album || $image->album->privacy === 'public') && !$image->isRejected();

        // 🛡️ SECURITY LAYER: If image is PRIVATE or on LOCAL, always use secure server-side routes.
        // This ensures private images never pass through ImageKit (Third-party CDN).
        if (!$isPublic || app()->environment('local') || !$isInCloud) {
            if ($this->canAccessOriginal($image) || $isPublic) {
                if (in_array($context, ['gallery', 'preview', 'thumbnail', 'avatar', 'icon', 'square'])) {
                    return $this->generateSecurePreviewUrl($image);
                }
                if (in_array($context, ['original', 'source'])) {
                    return $this->generateSecureOriginalUrl($image);
                }
            }
            return asset('images/locked.png');
        }

        // 🚀 PERFORMANCE LAYER: For PUBLIC CLOUD images, use ImageKit for CDN speed & WebP.
        $imageKit = app(\App\Services\Core\ImageKitService::class);
        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path;

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
                return $imageKit->getOptimizedUrl($path, 150, 150);

            case 'thumbnail':
            case 'square':
                return $imageKit->getOptimizedUrl($path, 400, 400);

            case 'card':
                return $imageKit->getOptimizedUrl($path, 400, 300);

            case 'list':
                return $imageKit->getOptimizedUrl($path, 200, 150);

            case 'gallery':
            case 'preview':
                return $imageKit->getOptimizedUrl($path, 800);

            case 'placeholder':
                return $imageKit->getOptimizedUrl($path, 20, 20);

            case 'original':
            case 'source':
                return $this->generateSecureOriginalUrl($image);

            case 'gallery_watermarked':
                $transformations = [['width' => 800]];
                if (!Auth::check() || Auth::id() !== $image->user_id) {
                    $transformations[] = [
                        'overlayImage' => 'logo.png',
                        'overlayFocus' => 'bottom_right',
                        'overlayAlpha' => '40',
                        'overlayWidth' => '150',
                    ];
                }
                return $imageKit->getEnhancedUrl($path, $transformations);

            default:
                return $imageKit->getOptimizedUrl($path);
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
            now()->addMinutes(5),
            ['image' => $image->id, 'v' => optional($image->updated_at)->timestamp ?? time()]
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
            now()->addMinutes(10),  // Shorter TTL for better security
            ['image' => $image->id, 'v' => optional($image->updated_at)->timestamp ?? time()]
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
