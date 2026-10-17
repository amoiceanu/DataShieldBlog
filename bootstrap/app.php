<?php

use App\Http\Middleware\SiteAvailability;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [SiteAvailability::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontFlash(['subject', 'location', 'email', 'password', 'password_confirmation', 'token']);
        $exceptions->report(function (Throwable $e) {
            if (request()->is('verificare-date-personale*', 'admin/verificarea-datelor*')) {
                return false;
            }
        });
        $exceptions->respond(function ($response) {
            if (request()->is('verificare-date-personale*', 'admin/verificarea-datelor*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        });
    })->create();
