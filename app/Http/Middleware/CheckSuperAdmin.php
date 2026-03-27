<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     * Only allows super-admin users. Returns 404 to hide the admin path existence.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && (
            Auth::user()->role === 'super_admin' ||
            Auth::user()->can('access-admin-dashboard')
        )) {
            return $next($request);
        }

        // Return 404 to hide the existence of the admin path
        abort(404);
    }
}
