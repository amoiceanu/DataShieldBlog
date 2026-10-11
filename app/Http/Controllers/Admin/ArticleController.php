<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $categoryId = $request->integer('sectiune');
        $category = $categoryId > 0 ? Category::find($categoryId) : null;
        $articles = Article::with('category')->latest('updated_at');

        if ($category !== null) {
            $articles->where('category_id', $category->id);
        }

        return view('admin.articles.index', [
            'articles' => $articles->paginate(15)->withQueryString(),
            'filteredCategory' => $category,
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['article' => new Article(['status' => 'draft']), 'categories' => Category::orderBy('name')->get()]);
    }

    public function show(Article $article): View
    {
        return view('admin.articles.show', compact('article'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        Article::create($data);

        return redirect()->route('admin.articole.index')->with('success', 'Articolul a fost creat.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', ['article' => $article, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        $article->update($this->validatedData($request, $article));

        return redirect()->route('admin.articole.index')->with('success', 'Articolul a fost actualizat.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $article->delete();

        return back()->with('success', 'Articolul a fost șters.');
    }

    private function validatedData(Request $request, ?Article $article = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)],
            'excerpt' => ['required', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'reading_time' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_featured' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['title']);
        validator(['slug' => $data['slug']], ['slug' => ['required', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)]])->validate();
        $data['is_featured'] = $request->boolean('is_featured');
        $data['published_at'] = $data['status'] === 'published'
            ? ($data['published_at'] ?? $article?->published_at?->toDateString() ?? now()->toDateString())
            : ($data['published_at'] ?? null);

        return $data;
    }
}
