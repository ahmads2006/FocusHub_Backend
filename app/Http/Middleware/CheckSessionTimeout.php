<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionTimeout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // If "stay_logged_in" is NOT enabled, enforce 10-minute timeout
            if (!$user->stay_logged_in) {
                $lastActivity = session('last_activity');
                $timeoutMinutes = 10; // Default 10 minutes as requested

                if ($lastActivity && (time() - $lastActivity > ($timeoutMinutes * 60))) {
                    Auth::logout();
                    session()->flush();
                    
                    return redirect()->route('login')->with('warning', 'تم تسجيل خروجك لداعي الأمان بسبب الخمول لمده 10 دقائق.');
                }

                session(['last_activity' => time()]);
            }
        }

        return $next($request);
    }
}
