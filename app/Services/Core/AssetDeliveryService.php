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
            if (in_array($context, ['original', 'source'])) {
                $path = $image->storage?->imagekit_file_path ?? $image->storage?->path ?? $image->path;
                if ($path) {
                    $path = ltrim($path, '/');
                    if (str_starts_with(strtolower($path), 'opticvault/')) {
                        $path = substr($path, strlen('opticvault/'));
                    }
                }

                // 💎 RAW FILE ACCESS: Bypass ImageKit to get the literal raw file from DigitalOcean/S3.
                $disk = $image->storage?->disk ?: 's3';
                return $shouldSign 
                    ? Storage::disk($disk)->temporaryUrl($image->storage?->path ?? $image->path, now()->addMinutes(30))
                    : Storage::disk($disk)->url($image->storage?->path ?? $image->path);
            }

            if (in_array($context, ['gallery', 'preview', 'thumbnail', 'avatar', 'icon', 'square', 'card', 'list', 'placeholder'])) {
                return $this->generateSecurePreviewUrl($image, $context);
            }
        }

        // 📁 FALLBACK: If not in cloud or in local env, use secure server-side routes or local path.
        if ($this->canAccessOriginal($image) || $isPublic) {
            if (in_array($context, ['gallery', 'preview', 'thumbnail', 'avatar', 'icon', 'square', 'card', 'list', 'placeholder'])) {
                return $this->generateSecurePreviewUrl($image, $context);
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
     * TTL: 10 minutes. Always routes through the secure backend route to hide the direct cloud URLs.
     */
    protected function generateSecurePreviewUrl(Image $image, string $context = 'gallery'): string
    {
        // 📁 Always use the internal signed route for secure preview
        return URL::temporarySignedRoute(
            'assets.preview',
            now()->addMinutes(10),
            [
                'image' => $image->id, 
                'context' => $context,
                'v' => optional($image->updated_at)->timestamp ?? time()
            ]
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

        // 3. If there is a shared link session for the image or the album, it must dictate permission.
        $hasImageSharedLink = session()->has("shared_link_access_{$image->id}");
        $hasAlbumSharedLink = $image->album_id && session()->has("shared_link_access_album_{$image->album_id}");

        if ($hasImageSharedLink || $hasAlbumSharedLink) {
            if ($hasImageSharedLink) {
                return session("shared_link_access_{$image->id}") === 'download';
            }
            if ($hasAlbumSharedLink) {
                return session("shared_link_access_album_{$image->album_id}") === 'download';
            }
        }

        // 4. Check if the image belongs to an authorized album (public, collaborators, owner)
        if ($image->album) {
            $album = $image->album;
            $hasAlbumAccess = false;
            if ($album->privacy === 'public') {
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
