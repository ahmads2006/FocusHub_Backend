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
        $token = $request->route('token');
        $tokenHash = hash('sha256', $token);
        
        // 1. Check Redis for Ephemeral Links First
        $ephemeralData = \Illuminate\Support\Facades\Cache::get("ephemeral_link:{$tokenHash}");
        
        if ($ephemeralData) {
            $link = new SharedLink($ephemeralData);
            // Re-set the raw token since it's used in rotation/UI
            $link->exists = true; // Pretend it exists for logic that checks this
            $link->token = $token;
        } else {
            // 2. Fallback to MySQL for Persistent Links
            $link = SharedLink::where('token_hash', $tokenHash)->first();
        }

        if (!$link || $link->isExpired() || $link->isRevoked() || $link->isLimitReached()) {
            \Illuminate\Support\Facades\Log::warning("Shared link validation failed: ", [
                'has_link' => (bool)$link,
                'expired'  => $link ? $link->isExpired() : null,
                'revoked'  => $link ? $link->isRevoked() : null,
                'limit'    => $link ? $link->isLimitReached() : null,
                'token'    => $token,
                'hash'     => $tokenHash
            ]);
            abort(404, 'Shared link is invalid or expired.');
        }

        // 🛡️ SECURITY ENFORCEMENT: Mandatory session locking and token rotation for all links.
        $sessionId = $request->session()->getId();

            if (empty($link->session_id)) {
                // First visit: lock link to this session and rotate token
                $newToken = \Illuminate\Support\Str::random(64);
                
                if (!$link->id) { // Ephemeral/Redis link
                    $newTokenHash = hash('sha256', $newToken);
                    $link->session_id = $sessionId;
                    $link->token = $newToken;
                    
                    $ttl = $link->expires_at ? now()->diffInSeconds($link->expires_at) : 3600;
                    \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$newTokenHash}", $link->toArray(), $ttl);
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

                // Redirect to the new secure URL
                return redirect()->route('shared_link.show', $newToken);
            } else {
                // Secondary visits: verify session matches
                if ($link->session_id !== $sessionId) {
                    // 🚨 LEAK DETECTED: Notification logic
                    $metadata = [
                        'ip'         => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'accessed_at'=> now()->toDateTimeString(),
                        'type'       => 'Session Mismatch (Possible Leak)'
                    ];
                    
                    $owner = $link->shareable?->user;
                    if ($owner) {
                        $owner->notify(new \App\Notifications\SharedLinkLeakDetected($link, $metadata));
                    }

                    \Illuminate\Support\Facades\Log::error("Shared link session mismatch: ", [
                        'link_session' => $link->session_id,
                        'current_session' => $sessionId,
                        'token' => $token
                    ]);

                    abort(403, 'عذراً، هذا الرابط مخصص لجهاز آخر فقط. تم إبلاغ المصور بمحاولة الدخول هذه لحماية الخصوصية.');
                }
            }

        // Use token hash as fallback ID for ephemeral links
        $authId = $link->id ?? $tokenHash;

        // Handle password protection
        if ($link->password && !$request->session()->get("link_auth_{$authId}")) {
            // If it's the POST request for password verification, let it through to controller
            if ($request->isMethod('post') && $request->has('password')) {
                // Store link in request for controller usage
                $request->attributes->set('shared_link', $link);
                $request->attributes->set('shared_link_auth_id', $authId);
                return $next($request);
            }
            
            return response()->view('shared_links.password', ['link' => $link]);
        }

        // Increment access count (unless we just rotated the token and redirected)
        if (!$request->session()->has("rotated_link_" . ($link->id ?? "redis"))) {
            if (!$link->id) {
                $link->access_count++;
                $link->last_accessed_at = now();
                $ttl = $link->expires_at ? now()->diffInSeconds($link->expires_at) : 3600;
                \Illuminate\Support\Facades\Cache::put("ephemeral_link:{$tokenHash}", $link->toArray(), $ttl);
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
