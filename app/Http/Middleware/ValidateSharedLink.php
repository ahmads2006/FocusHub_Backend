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
        
        $link = SharedLink::where('token_hash', hash('sha256', $token))->first();

        if (!$link || $link->isExpired() || $link->isRevoked() || $link->isLimitReached()) {
            abort(404, 'Shared link is invalid or expired.');
        }

        // Handle token rotation and session locking
        if ($link->auto_rotate) {
            $sessionId = $request->session()->getId();

            if (empty($link->session_id)) {
                // First visit: lock link to this session and rotate token
                $newToken = \Illuminate\Support\Str::random(64);
                
                $link->update([
                    'session_id' => $sessionId,
                    'token' => $newToken,
                ]);

                // Store an indicator that we just rotated the token
                // to avoid incrementing access count twice on redirect
                $request->session()->flash("rotated_link_{$link->id}", true);

                // Redirect to the new secure URL
                return redirect()->route('shared.link.show', $newToken);
            } else {
                // Secondary visits: verify session matches
                if ($link->session_id !== $sessionId) {
                    abort(403, 'هذا الرابط مخصص لجلسة أخرى غير مصرح لك بفتحه.');
                }
            }
        }

        // Handle password protection
        if ($link->password && !$request->session()->get("link_auth_{$link->id}")) {
            // If it's the POST request for password verification, let it through to controller
            if ($request->isMethod('post') && $request->has('password')) {
                return $next($request);
            }
            
            return response()->view('shared_links.password', ['link' => $link]);
        }

        // Increment access count (unless we just rotated the token and redirected)
        if (!$request->session()->has("rotated_link_{$link->id}")) {
            $link->increment('access_count');
            $link->update(['last_accessed_at' => now()]);
        }

        // Store link in request for controller usage
        $request->attributes->set('shared_link', $link);

        return $next($request);
    }
}
