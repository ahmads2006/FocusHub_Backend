<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
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
        // Check that the user is logged in and has the admin access permission
        if (Auth::check() && (Auth::user()->role === 'super_admin' || Auth::user()->can('access-admin-dashboard'))) {
            return $next($request);
        }
 
        abort(403, 'عذراً، الرتبة المخصصة لك في قاعدة البيانات لا تسمح لك بالوصول لهذه الصفحة.');
    }
}
