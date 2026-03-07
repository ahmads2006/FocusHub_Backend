<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVerified
{
    /**
     * Handle an incoming request.
     * يمنع أي مستخدم من الوصول إلا إذا كان حقل is_verified يساوي 1.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->is_verified) {
            return redirect()->route('verify.code')
                ->withErrors(['code' => 'يجب عليك التحقق من بريدك الإلكتروني أولاً.']);
        }

        return $next($request);
    }
}
