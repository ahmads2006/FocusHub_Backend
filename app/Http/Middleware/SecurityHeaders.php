<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and inject security headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 🛡️ Prevent site from being embedded in frames (Clickjacking)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // 🛡️ Prevent MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 🛡️ Enable browser XSS filtering
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 🛡️ Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 🛡️ Disable unneeded browser features (Privacy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // 🛡️ Content Security Policy — restrict script/style sources to prevent XSS
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https://ik.imagekit.io https://*.digitaloceanspaces.com https://*.opalshot.studio https://*.amazonaws.com https://images.unsplash.com",
            "connect-src 'self' https://*.opalshot.studio wss://*.opalshot.studio https://api.opalshot.studio wss://api.opalshot.studio https://ik.imagekit.io https://*.digitaloceanspaces.com",
            "frame-ancestors 'self'",
            "upgrade-insecure-requests"
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // 🛡️ HTTP Strict Transport Security — force HTTPS for 1 year + subdomains
        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
