<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Services\DailyTipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function home(DailyTipService $dailyTips): View
    {
        $articles = Article::with('category')->where('status', 'published')->whereDate('published_at', '<=', now())->latest('published_at')->take(3)->get();
        $featured = Article::with('category')->where('status', 'published')->whereDate('published_at', '<=', now())->where('is_featured', true)->first() ?? $articles->first();
        return view('pages.home', ['articles' => $articles, 'featured' => $featured, 'categories' => $this->categories(), 'dailyTip' => $dailyTips->today()]);
    }

    public function index(Request $request): View|RedirectResponse
    {
        $categoryAliases = [
            'securitate-online' => 'securitate-dispozitive',
            'parole' => 'parole-autentificare',
            'social-media' => 'retele-sociale',
            'ghiduri' => 'ghiduri-practice',
            'phishing' => 'fraude-online',
        ];
        $category = $request->string('categorie')->toString();
        if (isset($categoryAliases[$category])) {
            $parameters = $request->query();
            $parameters['categorie'] = $categoryAliases[$category];
            return redirect()->route('articles.index', $parameters, 301);
        }

        $query = Article::with('category')->where('status', 'published')->whereDate('published_at', '<=', now())->latest('published_at');
        if ($request->filled('q')) $query->where(fn ($q) => $q->where('title', 'like', '%'.$request->string('q')->toString().'%')->orWhere('excerpt', 'like', '%'.$request->string('q')->toString().'%'));
        if ($request->filled('categorie')) $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('categorie')->toString()));
        return view('pages.articles', ['articles' => $query->paginate(9)->withQueryString(), 'categories' => $this->categories()]);
    }

    public function show(Article $article): View
    {
        abort_unless($article->status === 'published' && $article->published_at && ! $article->published_at->isFuture(), 404);
        $article->load('category');
        $recommended = Article::with('category')->where('status', 'published')->whereDate('published_at', '<=', now())->whereKeyNot($article->id)->latest('published_at')->take(3)->get();
        return view('pages.article', compact('article', 'recommended'));
    }

    private function categories()
    {
        return Cache::remember('blog.categories', 3600, fn () => Category::whereHas('articles', fn ($query) => $query->where('status', 'published')->whereDate('published_at', '<=', now()))
            ->withCount(['articles' => fn ($query) => $query->where('status', 'published')->whereDate('published_at', '<=', now())])
            ->orderBy('name')->get());
    }
}
