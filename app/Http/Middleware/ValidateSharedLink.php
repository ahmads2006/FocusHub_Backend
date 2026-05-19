<?php

namespace App\Http\Middleware;

use App\Models\SharedLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSharedLink
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 🛡️ CRITICAL: Skip validation for the verification route itself to avoid recursive loops
        if ($request->is('*/verify') || $request->routeIs('shared_link.verify')) {
            return $next($request);
        }

        $token = $request->route('token');
        $tokenHash = hash('sha256', $token);

        // 📝 DEBUG: Log Headers
        \Illuminate\Support\Facades\Log::info("Shared Link Request Headers:", [
            'url' => $request->fullUrl(),
            'headers' => collect($request->headers->all())->map(fn($v) => $v[0])->toArray(),
        ]);

        // 1. Check Redis for Ephemeral Links First
        $ephemeralData = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$tokenHash}");

        if ($ephemeralData) {
            $link = new SharedLink();
            // 🛡️ Data from Redis is already encrypted/casted, so set as Raw Attributes
            $link->setRawAttributes($ephemeralData);
            $link->exists = true;

            // 🔗 Load shareable relationship manually
            if ($link->shareable_id && $link->shareable_type) {
                try {
                    $modelClass = $link->shareable_type;
                    if (class_exists($modelClass)) {
                        $link->setRelation('shareable', $modelClass::find($link->shareable_id));
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to load shareable: " . $e->getMessage());
                }
            }

            // Override the encrypted token with the raw one from URL for this instance
            $link->token = $token;
        } else {
            // 2. Fallback to MySQL for Persistent Links
            $link = SharedLink::where('token_hash', $tokenHash)->first();
        }

        if (!$link || $link->isExpired() || $link->isRevoked() || $link->isLimitReached() || !$link->shareable) {
            \Illuminate\Support\Facades\Log::warning("Shared link validation failed: ", [
                'has_link' => (bool) $link,
                'has_shareable' => $link ? (bool) $link->shareable : false,
                'expired' => $link ? $link->isExpired() : null,
                'token' => $token
            ]);
            abort(404, 'Shared link is invalid or expired.');
        }

        // 🔑 CRITICAL: Assign a stable persistent_id BEFORE token rotation
        // This ensures the password auth key remains valid even after token changes.
        // Must be done for ALL link types (DB and ephemeral) that have a password.
        if ($link->password && empty($link->persistent_id)) {
            $link->persistent_id = md5($tokenHash); // Derived from original token hash
            if ($link->id) {
                // DB link: persist the stable ID
                $link->saveQuietly(); // Use saveQuietly to avoid triggering token_hash recalculation
            } else {
                // Ephemeral link: save back to cache
                $ttl = $link->expires_at ? now()->diffInSeconds($link->expires_at) : 3600;
                if ($ttl > 0) {
                    \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$tokenHash}", $link->getAttributes(), $ttl);
                }
            }
        }

        // 🔒 3. Password Protection Logic
        // persistent_id was already assigned above
        $authKeyId = $link->persistent_id ?? $tokenHash;
        $authKey = "shared_link_auth_" . $authKeyId;
        $ipAuthKey = "shared_link_fp_auth_" . $authKeyId . "_" . md5($request->userAgent());

        $hasSessionAuth = $request->session()->has($authKey);
        $hasIpAuth = \Illuminate\Support\Facades\Cache::has($ipAuthKey);
        $cookieName = "sl_auth_" . substr($authKeyId, 0, 8);
        $hasCookieAuth = $request->hasCookie($cookieName);

        if ($link->password && !$hasSessionAuth && !$hasIpAuth && !$hasCookieAuth) {
            \Illuminate\Support\Facades\Log::info("Shared link password required:", [
                'ip' => $request->ip(),
                'ua_md5' => md5($request->userAgent()),
                'authKey' => $authKey,
                'fpKey' => $ipAuthKey,
                'has_cookie' => $hasCookieAuth
            ]);

            return response()->json([
                'message' => 'Password required',
                'auth_required' => true,
                'auth_key_debug' => $authKey,
                'target' => $link->label ?? 'العنصر المشترك'
            ], 403);
        }

        // ✅ CRITICAL: Tell the controller that password auth passed (or no password needed)
        // Without this, the controller's own check for 'shared_link_authenticated' always fails!
        $request->attributes->set('shared_link_authenticated', true);

        // If IP auth exists but session auth doesn't, sync it back to session for consistency
        if ($hasIpAuth && !$hasSessionAuth) {
            $request->session()->put($authKey, true);
        }

        // 🛡️ SECURITY ENFORCEMENT: Session locking & Token rotation.
        // Mandatory for Albums and Private Images. Disabled ONLY for Public Images.
        $sessionId = $request->session()->getId();
        $isOwner = auth()->check() && $link->shareable && isset($link->shareable->user_id) && $link->shareable->user_id === auth()->id();

        $isPublicImage = $link->shareable instanceof \App\Models\Image && $link->shareable->privacy === 'public';

        // As requested: Disable device lock ONLY for public images.
        // If an image changes from public to private, this will automatically become true.
        $shouldLock = !$isPublicImage;

        if ($shouldLock && !$isOwner) {
            if (empty($link->session_id)) {
                // First visit: lock link to this session and rotate token
                $newToken = \Illuminate\Support\Str::random(64);

                if (!$link->id) { // Ephemeral/Redis link
                    $newTokenHash = hash('sha256', $newToken);
                    $link->session_id = $sessionId;
                    $link->token = $newToken;

                    $ttl = $link->expires_at ? now()->diffInSeconds($link->expires_at) : 3600;
                    \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$newTokenHash}", $link->getAttributes(), $ttl);
                    \Illuminate\Support\Facades\Cache::forget("ephemeral_link:{$tokenHash}");
                } else {
                    $link->update([
                        'session_id' => $sessionId,
                        'token' => $newToken,
                    ]);
                }

                // Store an indicator that we just rotated the token
                // to avoid incrementing access count twice on redirect
                $request->session()->flash("rotated_link_" . ($link->id ?? "redis"), true);

                // For API/AJAX requests: return JSON with new token instead of redirect
                // (Axios can't follow redirects with session cookies properly)
                if ($request->expectsJson() || $request->ajax()) {
                    // Re-run validation with the new token by making a self-call
                    // Store the link data in request for the controller
                    $request->attributes->set('shared_link', $link);
                    return $next($request);
                }

                // For browser requests: redirect to the new secure URL
                return redirect()->route('shared_link.show', $newToken);
            } else {
                // Secondary visits: verify session matches
                if ($link->session_id !== $sessionId) {
                    // 🚨 LEAK DETECTED: Notification logic
                    $metadata = [
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'accessed_at' => now()->toDateTimeString(),
                        'type' => 'Session Mismatch (Possible Leak)'
                    ];

                    $owner = $link->shareable?->user;
                    if ($owner) {
                        try {
                            $owner->notify(new \App\Notifications\SharedLinkLeakDetected($link, $metadata));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error("Failed to send leak notification: " . $e->getMessage());
                        }
                    }

                    \Illuminate\Support\Facades\Log::error("Shared link session mismatch: ", [
                        'link_session' => $link->session_id,
                        'current_session' => $sessionId,
                        'token' => $token
                    ]);

                    abort(403, 'عذراً، هذا الرابط مخصص لجهاز آخر فقط. تم إبلاغ المصور بمحاولة الدخول هذه لحماية الخصوصية.');
                }
            }
        }

        // Use token hash as fallback ID for ephemeral links
        $authId = $link->id ?? $tokenHash;

        // Increment access count (unless we just rotated the token and redirected, or it's the owner)
        if (!$request->session()->has("rotated_link_" . ($link->id ?? "redis")) && !$isOwner) {
            if (!$link->id) {
                $link->access_count++;
                $link->last_accessed_at = now();
                $ttl = $link->expires_at ? now()->diffInSeconds($link->expires_at) : 3600;
                \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$tokenHash}", $link->getAttributes(), $ttl);
            } else {
                $link->increment('access_count');
                $link->update(['last_accessed_at' => now()]);
            }
        }

        // Persist access permissions in session for the AssetAccessController/AssetDeliveryService
        $shareable = $link->shareable;
        if ($shareable instanceof \App\Models\Image) {
            $this->setSessionAccess($request, $shareable->id, $link);
        } elseif ($shareable instanceof \App\Models\Album) {
            // Track album access specifically for AlbumPolicy
            $request->session()->put("shared_link_access_album_{$shareable->id}", $link->permission);

            foreach ($shareable->photos as $photo) {
                $this->setSessionAccess($request, $photo->id, $link);
            }
        }

        // Store link in request for controller usage
        $request->attributes->set('shared_link', $link);

        return $next($request);
    }

    /**
     * Set session access details for a specific image.
     */
    protected function setSessionAccess(Request $request, string $imageId, SharedLink $link): void
    {
        $request->session()->put("shared_link_access_{$imageId}", $link->permission);
        $request->session()->put("shared_link_watermark_{$imageId}", $link->require_watermark);
        $request->session()->put("shared_link_id_{$imageId}", $link->id);
    }
}
