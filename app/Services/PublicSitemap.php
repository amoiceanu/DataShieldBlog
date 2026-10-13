<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Collection;

class PublicSitemap
{
    private const ORIGIN = 'https://securitatedigitala.ro';

    /** @return Collection<int, array{loc:string,lastmod?:string}> */
    public function entries(): Collection
    {
        // Keep the existing six public pages in the sitemap while adding the
        // canonical category landing pages and published articles.
        $entries = collect([
            ['loc' => self::ORIGIN.'/'],
            ['loc' => self::ORIGIN.route('articles.index', [], absolute: false)],
            ['loc' => self::ORIGIN.route('about', [], absolute: false)],
            ['loc' => self::ORIGIN.route('contact', [], absolute: false)],
            ['loc' => self::ORIGIN.route('privacy', [], absolute: false)],
            ['loc' => self::ORIGIN.route('cookies', [], absolute: false)],
        ]);

        $categories = Category::query()
            ->whereHas('articles', fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now()))
            ->withMax(['articles as content_updated_at' => fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now())], 'updated_at')
            ->orderBy('slug')
            ->get();
        foreach ($categories as $category) {
            $entry = ['loc' => self::ORIGIN.route('sections.show', $category->slug, absolute: false)];
            if ($category->content_updated_at) {
                $entry['lastmod'] = Carbon::parse($category->content_updated_at)->toAtomString();
            }
            $entries->push($entry);
        }

        $articles = Article::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', now())
            ->orderBy('slug')
            ->get();
        foreach ($articles as $article) {
            $entry = ['loc' => self::ORIGIN.route('articles.show', $article->slug, absolute: false)];
            if ($article->updated_at) {
                $entry['lastmod'] = $article->updated_at->toAtomString();
            }
            $entries->push($entry);
        }

        return $entries->unique('loc')->values();
    }
}
