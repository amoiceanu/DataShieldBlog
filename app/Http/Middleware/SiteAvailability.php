<?php

namespace App\Http\Middleware;

use App\Services\SitePages;
use Closure;
use Illuminate\Http\Request;

class SiteAvailability
{
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route()?->getName();
        $key = match (true) {
            $route === 'home' => 'home',
            in_array($route, ['articles.index', 'articles.show']) => 'articles',
            in_array($route, ['contact', 'contact.store']) => 'contact',
            in_array($route, ['about', 'privacy', 'cookies']) => $route,
            str_starts_with($route ?? '', 'exposure.') && ! str_starts_with($route ?? '', 'exposure.admin') => 'exposure',
            default => null,
        };
        if ($key) {
            $pages = app(SitePages::class);
            abort_unless($pages->enabled($key), 404);
            if ($route === 'articles.index' && $request->query('categorie') === 'ghiduri') {
                abort_unless($pages->enabled('guides'), 404);
            }
        }
        $response = $next($request);
        // Availability and navigation changes must be reflected immediately, without public cached pages.
        if ($key || $route === 'sitemap') {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
