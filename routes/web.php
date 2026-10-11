<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Response;

Route::get('/', [BlogController::class, 'home'])->name('home');
Route::get('/articole', [BlogController::class, 'index'])->name('articles.index');
Route::get('/articole/{article:slug}', [BlogController::class, 'show'])->name('articles.show');
Route::view('/despre', 'pages.about')->name('about');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login.store');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->middleware('auth')->name('admin.logout');
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function (): void {
    Route::redirect('/', '/admin/articole')->name('dashboard');
    Route::resource('articole', AdminArticleController::class)->except('show')->parameters(['articole' => 'article']);
    Route::resource('sectiuni', AdminCategoryController::class)->except('show')->parameters(['sectiuni' => 'category']);
});
Route::view('/politica-de-confidentialitate', 'pages.privacy')->name('privacy');
Route::view('/politica-cookies', 'pages.cookies')->name('cookies');
Route::get('/sitemap.xml', function () {
    $siteUrl = 'https://securitatedigitala.ro';
    $canonicalRoute = fn (string $name, mixed $parameters = []) => $siteUrl.route($name, $parameters, false);
    $urls = [
        ['loc' => $canonicalRoute('home')],
        ['loc' => $canonicalRoute('articles.index')],
        ['loc' => $canonicalRoute('about')],
        ['loc' => $canonicalRoute('contact')],
        ['loc' => $canonicalRoute('privacy')],
        ['loc' => $canonicalRoute('cookies')],
    ];
    foreach (\App\Models\Article::where('status', 'published')->whereDate('published_at', '<=', now())->get() as $article) {
        $urls[] = ['loc' => $canonicalRoute('articles.show', $article), 'lastmod' => $article->updated_at->toAtomString()];
    }
    return response()->view('sitemap', compact('urls'))->header('Content-Type', 'application/xml');
})->name('sitemap');
Route::get('/robots.txt', fn () => Response::make("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://securitatedigitala.ro/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']))->name('robots');
