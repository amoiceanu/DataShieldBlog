<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SiteAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->is_exposure_admin, 403);

        return $next($request);
    }
}
