<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectAdminPanel
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check that the user is logged in and their rank in the database is super_admin
        if (auth()->check() && auth()->user()->role === 'super_admin') {
            return $next($request);
        }

        // If they try to log in as a regular user (even if their name is super admin), ban them immediately
        abort(403, 'Sorry, your assigned rank in the database does not allow you to access this page.');
    }
}
