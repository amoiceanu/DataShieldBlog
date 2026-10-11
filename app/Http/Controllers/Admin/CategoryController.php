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

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('articles')->orderBy('name')->paginate(15),
            'stats' => [
                'articles' => Article::count(),
                'published' => Article::where('status', 'published')->count(),
                'drafts' => Article::where('status', 'draft')->count(),
                'sections' => Category::count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validatedData($request));
        cache()->forget('blog.categories');

        return redirect()->route('admin.sectiuni.index')->with('success', 'Secțiunea a fost creată.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validatedData($request, $category));
        cache()->forget('blog.categories');

        return redirect()->route('admin.sectiuni.index')->with('success', 'Secțiunea a fost actualizată.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->articles()->exists()) {
            return back()->withErrors(['category' => 'Mută sau șterge articolele din această secțiune înainte de ștergere.']);
        }

        $category->delete();
        cache()->forget('blog.categories');

        return back()->with('success', 'Secțiunea a fost ștearsă.');
    }

    private function validatedData(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['name']);
        validator(['slug' => $data['slug']], ['slug' => ['required', 'max:255', Rule::unique('categories', 'slug')->ignore($category?->id)]])->validate();

        return $data;
    }
}
