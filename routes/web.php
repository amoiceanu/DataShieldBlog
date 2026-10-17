<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ExposureAdminController;
use App\Http\Controllers\ExposureController;
use App\Http\Controllers\SitePagesAdminController;
use App\Http\Middleware\ExposureAccess;
use App\Http\Middleware\SiteAdmin;
use App\Models\Article;
use App\Services\SitePages;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', [BlogController::class, 'home'])->name('home');
Route::get('/articole', [BlogController::class, 'index'])->name('articles.index');
Route::get('/articole/{article:slug}', [BlogController::class, 'show'])->name('articles.show');
Route::view('/despre', 'pages.about')->name('about');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::view('/politica-de-confidentialitate', 'pages.privacy')->name('privacy');
Route::view('/politica-cookies', 'pages.cookies')->name('cookies');
Route::get('/sitemap.xml', function () {
    $pages = app(SitePages::class);
    $urls = [];
    foreach (['home', 'articles', 'about', 'contact', 'privacy', 'cookies'] as $key) {
        if ($pages->enabled($key)) {
            $urls[] = ['loc' => route(config('site_pages.'.$key.'.route'))];
        }
    }
    if ($pages->enabled('articles')) {
        foreach (Article::whereDate('published_at', '<=', now())->get() as $article) {
            $urls[] = ['loc' => route('articles.show', $article), 'lastmod' => $article->updated_at->toAtomString()];
        }
    }

    return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/robots.txt', fn () => Response::make("User-agent: *\nAllow: /\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain']))->name('robots');

// Private exposure workflows: no subject or email in application URLs.
Route::middleware(ExposureAccess::class)->prefix('verificare-date-personale')->name('exposure.')->group(function () {
    Route::get('/', [ExposureController::class, 'index'])->name('index');
    Route::post('/', [ExposureController::class, 'store'])->name('store');
    Route::get('/confirmare/{token}', [ExposureController::class, 'verificationLink'])->middleware('throttle:30,1')->name('verify');
    Route::get('/confirmare', [ExposureController::class, 'confirmation'])->name('confirmation');
    Route::post('/confirmare', [ExposureController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
    Route::get('/raport/{id}', [ExposureController::class, 'show'])->middleware('throttle:120,1')->name('report');
    Route::get('/raport/{id}/status', [ExposureController::class, 'status'])->middleware('throttle:120,1')->name('status');
    Route::post('/raport/{id}/retrimite', [ExposureController::class, 'resend'])->name('resend');
    Route::post('/raport/{id}/clasificare/{index}', [ExposureController::class, 'classify'])->whereNumber('index')->middleware('throttle:30,1')->name('classify');
    Route::delete('/raport/{id}', [ExposureController::class, 'destroy'])->name('destroy');
});
Route::middleware([ExposureAccess::class.':admin', 'auth.basic', SiteAdmin::class, 'throttle:30,1'])->group(function () {
    Route::redirect('/admin', '/admin/pagini-si-module');
    Route::get('/admin/pagini-si-module', [SitePagesAdminController::class, 'index'])->name('admin.pages');
    Route::put('/admin/pagini-si-module', [SitePagesAdminController::class, 'update'])->name('admin.pages.update');
    Route::get('/admin/verificarea-datelor', [ExposureAdminController::class, 'index'])->name('exposure.admin');
    Route::put('/admin/verificarea-datelor', [ExposureAdminController::class, 'update'])->name('exposure.admin.update');
});
