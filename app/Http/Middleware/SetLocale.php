<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check for 'lang' query parameter
        if ($request->has('lang')) {
            $lang = $request->query('lang');
            if (in_array($lang, ['en', 'ar'])) {
                Session::put('vault_locale', $lang);
            }
        }

        // 2. Set the application locale from session
        $locale = Session::get('vault_locale', config('app.locale'));
        App::setLocale($locale);

        return $next($request);
    }
}
