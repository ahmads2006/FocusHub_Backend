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

        // 2. Determine the locale
        $locale = config('app.locale');
        
        if ($request->hasHeader('Accept-Language')) {
            $headerLang = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($headerLang, ['en', 'ar'])) {
                $locale = $headerLang;
            }
        } elseif (Session::has('vault_locale')) {
            $locale = Session::get('vault_locale');
        }

        // 3. Set the application locale
        App::setLocale($locale);

        return $next($request);
    }
}
