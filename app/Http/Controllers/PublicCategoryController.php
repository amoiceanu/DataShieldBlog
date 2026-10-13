<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Request $request, Category $category): View
    {
        $articles = $category->articles()
            ->with('category')
            ->where('status', 'published')
            ->whereDate('published_at', '<=', now())
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        abort_if($articles->total() === 0, 404);

        return view('pages.category', compact('category', 'articles'));
    }
}
