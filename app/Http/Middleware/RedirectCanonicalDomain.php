<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strtolower($request->getHost()) === 'www.securitatedigitala.ro') {
            return redirect()->away('https://securitatedigitala.ro'.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
