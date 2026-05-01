<?php

namespace App\Services\Core;

use App\Models\Image;
use Illuminate\Support\Facades\Storage;
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
        $isInCloud = !empty($image->storage?->imagekit_file_id) || 
                     !empty($image->storage?->imagekit_file_path) || 
                     ($image->storage?->disk === 'spaces' || $image->storage?->disk === 's3');
        $isPublic = $image->privacy === 'public' && (!$image->album || $image->album->privacy === 'public') && !$image->isRejected();


        // 🛡️ SECURITY LAYER: If image is PRIVATE or not in cloud, use secure server-side routes.
        // Public cloud images go directly to ImageKit for WebP optimization.
        $useImageKit = $isPublic && $isInCloud && !app()->environment('local');

        if (!$useImageKit) {
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
        $path = $image->storage?->imagekit_file_path ?? $image->storage?->path ?? $image->path;

        if ($path) {
            $path = ltrim($path, '/');
            if (str_starts_with(strtolower($path), 'opticvault/')) {
                $path = substr($path, strlen('opticvault/'));
            }
        }

        if (in_array($context, ['original', 'source'])) {
            return $imageKit->getOptimizedUrl($path);
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

            case 'srcset':
                // Generate a responsive srcset for 400w, 800w, and 1200w
                $w400 = $imageKit->getOptimizedUrl($path, 400);
                $w800 = $imageKit->getOptimizedUrl($path, 800);
                $w1200 = $imageKit->getOptimizedUrl($path, 1200);
                return "{$w400} 400w, {$w800} 800w, {$w1200} 1200w";

            case 'placeholder':
                return $imageKit->getOptimizedUrl($path, 20, 20);

            case 'original':
            case 'source':
                // 🛡️ Even for original view, we prefer ImageKit optimized WebP for speed
                // unless the user specifically needs the raw source file.
                return $imageKit->getOptimizedUrl($path);

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
     * Resolve the storage disk and file path for an image.
     * Returns [disk_name, file_path] or null if unresolvable.
     */
    protected function resolveStorageLocation(Image $image): ?array
    {
        $disk = $image->storage?->disk;
        $path = $image->storage?->path ?? $image->path;

        if (!$path) {
            return null;
        }

        // Normalize: accept 'spaces' as an alias for the 's3' disk config
        if ($disk === 'spaces') {
            $disk = 's3';
        }

        // Default to 's3' if image is known to be in cloud
        if (!$disk || !in_array($disk, ['s3', 'local', 'public'])) {
            $disk = 's3';
        }

        return [$disk, $path];
    }

    /**
     * Generate a pre-signed temporary URL for the original high-res file (download).
     * TTL: 5 minutes. For cloud disks, the URL points directly to S3/Spaces
     * with an embedded signature — no backend proxy needed.
     */
    protected function generateSecureOriginalUrl(Image $image): string
    {
        $location = $this->resolveStorageLocation($image);

        if ($location) {
            [$disk, $path] = $location;

            // ☁️ Cloud disk: generate a pre-signed URL directly from S3/Spaces
            if ($disk === 's3') {
                try {
                    return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(5));
                } catch (\RuntimeException $e) {
                    // Driver doesn't support temporaryUrl (e.g. local), fall through
                }
            }
        }

        // 📁 Fallback: internal signed route for local/public disk
        return URL::temporarySignedRoute(
            'assets.original',
            now()->addMinutes(5),
            ['image' => $image->id, 'v' => optional($image->updated_at)->timestamp ?? time()]
        );
    }

    /**
     * Generate a pre-signed temporary URL for inline preview display in <img> tags.
     * TTL: 10 minutes. For cloud disks, the URL points directly to S3/Spaces
     * with an embedded signature — the browser loads from the CDN, not the backend.
     */
    protected function generateSecurePreviewUrl(Image $image): string
    {
        $location = $this->resolveStorageLocation($image);

        if ($location) {
            [$disk, $path] = $location;

            // ☁️ Cloud disk: generate a pre-signed URL directly from S3/Spaces
            if ($disk === 's3') {
                try {
                    return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(10));
                } catch (\RuntimeException $e) {
                    // Driver doesn't support temporaryUrl (e.g. local), fall through
                }
            }
        }

        // 📁 Fallback: internal signed route for local/public disk
        return URL::temporarySignedRoute(
            'assets.preview',
            now()->addMinutes(10),
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
