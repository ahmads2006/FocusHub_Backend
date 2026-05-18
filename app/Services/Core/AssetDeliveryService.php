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
        // 🚀 SMART ROUTING (v6.0): Detect cloud presence by ImageKit path OR storage path.
        // NOTE: disk column is often NULL in DB, so we can't rely on it alone.
        $isInCloud = !empty($image->storage?->imagekit_file_id) || 
                     !empty($image->storage?->imagekit_file_path) || 
                     !empty($image->storage?->path) ||
                     in_array($image->storage?->disk, ['spaces', 's3']);
        // NOTE: albums table has NO privacy column — don't reference album->privacy.
        $isPublic = $image->privacy === 'public' && !$image->isRejected();

        // 🛡️ SECURITY LAYER: Determine if we should sign the URL.
        // Private images MUST be signed to prevent unauthorized access.
        $shouldSign = !$isPublic;

        // 🚀 PERFORMANCE LAYER: Use ImageKit for ALL cloud images to benefit from CDN & Optimization.
        // NOTE: We only use ImageKit if the image is actually in the cloud and we're not in local env.
        if ($isInCloud && !app()->environment('local')) {
            $imageKit = app(\App\Services\Core\ImageKitService::class);
            $path = $image->storage?->imagekit_file_path ?? $image->storage?->path ?? $image->path;

            if ($path) {
                $path = ltrim($path, '/');
                if (str_starts_with(strtolower($path), 'opticvault/')) {
                    $path = substr($path, strlen('opticvault/'));
                }
            }

            $isGif = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'gif';

            // Define base transformations for optimization
            // 🎞️ GIF PROTECTOR: If it's a GIF, we MUST NOT use 'format: auto' as it often flattens the animation.
            $baseTransformations = $isGif 
                ? [['quality' => 'auto']] // Keep original format for GIFs
                : [['format' => 'auto', 'quality' => 'auto', 'progressive' => 'true']];

            if (in_array($context, ['original', 'source'])) {
                // 💎 RAW FILE ACCESS: Bypass ImageKit to get the literal raw file from DigitalOcean/S3.
                $disk = $image->storage?->disk ?: 's3';
                return $shouldSign 
                    ? Storage::disk($disk)->temporaryUrl($image->storage?->path ?? $image->path, now()->addMinutes(30))
                    : Storage::disk($disk)->url($image->storage?->path ?? $image->path);
            }

            $width = null;
            $height = null;

            switch ($context) {
                case 'avatar':
                case 'icon':
                    $width = 150;
                    $height = 150;
                    break;

                case 'thumbnail':
                case 'square':
                    $width = 400;
                    $height = 400;
                    break;

                case 'card':
                    $width = 400;
                    $height = 300;
                    break;

                case 'list':
                    $width = 200;
                    $height = 150;
                    break;

                case 'gallery':
                case 'preview':
                    $width = 800;
                    break;

                case 'srcset':
                    $sizes = [400, 800, 1200, 1600];
                    $srcset = [];
                    foreach ($sizes as $s) {
                        $srcset[] = $imageKit->getOptimizedUrl($path, $s) . " {$s}w";
                    }
                    return implode(', ', $srcset);

                case 'placeholder':
                    $width = 20;
                    $height = 20;
                    break;

                default:
                    $width = 800;
                    break;
            }

            if ($shouldSign) {
                $transformations = array_merge($baseTransformations, [
                    array_filter(['width' => (string)$width, 'height' => (string)$height, 'crop' => 'at_max'])
                ]);
                return $imageKit->generateSignedUrl($path, $transformations);
            }

            return $imageKit->getOptimizedUrl($path, $width, $height);
        }

        // 📁 FALLBACK: If not in cloud or in local env, use secure server-side routes or local path.
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
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $filename = 'OpalShot-' . $image->id . ($ext ? '.' . $ext : '.jpg');
                    return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(5), [
                        'ResponseContentDisposition' => 'attachment; filename="' . $filename . '"'
                    ]);
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

        // 4. Check if the image belongs to an authorized album (public, shared link, collaborators, owner)
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

            if ($hasAlbumAccess && ($image->settings?->allow_download ?? true)) {
                return true;
            }
        }

        // 5. Public assets are downloadable if allowed by owner
        if ($image->privacy === 'public' && ($image->settings?->allow_download ?? true)) {
            return true;
        }

        return false;
    }
}
