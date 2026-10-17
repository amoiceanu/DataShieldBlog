<?php

namespace App\Http\Middleware;

use App\Services\Exposure\Settings;
use Closure;
use Illuminate\Http\Request;

class ExposureAccess
{
    public function handle(Request $request, Closure $next, string $scope = 'public')
    {
        config(['app.debug' => false]);
        if ($scope === 'public') {
            abort_unless(app(Settings::class)->all()['enabled'], 404);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
