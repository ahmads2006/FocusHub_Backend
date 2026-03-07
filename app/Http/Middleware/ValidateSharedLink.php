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
        
        $link = SharedLink::where('token', $token)->first();

        if (!$link || $link->isExpired() || $link->isRevoked() || $link->isLimitReached()) {
            abort(404, 'Shared link is invalid or expired.');
        }

        // Handle password protection
        if ($link->password && !$request->session()->get("link_auth_{$link->id}")) {
            // If it's the POST request for password verification, let it through to controller
            if ($request->isMethod('post') && $request->has('password')) {
                return $next($request);
            }
            
            return response()->view('shared_links.password', ['link' => $link]);
        }

        // Increment access count
        $link->increment('access_count');
        $link->update(['last_accessed_at' => now()]);

        // Store link in request for controller usage
        $request->attributes->set('shared_link', $link);

        return $next($request);
    }
}
