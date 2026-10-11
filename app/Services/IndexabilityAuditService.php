<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\IndexabilityAudit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IndexabilityAuditService
{
    private const ORIGIN = 'https://securitatedigitala.ro';

    public function candidates(): array
    {
        $rows = [[
            'title' => 'Pagina principală',
            'url' => self::ORIGIN.'/',
            'type' => 'Homepage',
            'content_updated_at' => null,
        ]];

        $categories = Category::query()
            ->whereHas('articles', fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now()))
            ->withMax(['articles as content_updated_at' => fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now())], 'updated_at')
            ->orderBy('name')
            ->get();
        foreach ($categories as $category) {
            $rows[] = [
                'title' => $category->name,
                'url' => self::ORIGIN.route('sections.show', $category->slug, false),
                'type' => 'Category',
                'content_updated_at' => $category->content_updated_at,
            ];
        }

        $articles = Article::query()
            ->with('category')
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereDate('published_at', '<=', now())
            ->orderBy('title')
            ->get();
        foreach ($articles as $article) {
            $rows[] = [
                'title' => $article->title,
                'url' => self::ORIGIN.route('articles.show', $article->slug, false),
                'type' => 'Article',
                'content_updated_at' => $article->updated_at,
                'category_url' => self::ORIGIN.route('sections.show', $article->category->slug, false),
            ];
        }

        return $rows;
    }

    public function run(IndexabilityAudit $audit): IndexabilityAudit
    {
        $audit->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $candidates = $this->candidates();
        $sitemapUrl = self::ORIGIN.'/sitemap.xml';
        $robotsUrl = self::ORIGIN.'/robots.txt';

        try {
            $sitemapResponse = Http::timeout(15)->get($sitemapUrl);
            $sitemapXml = $sitemapResponse->successful() ? @simplexml_load_string($sitemapResponse->body(), 'SimpleXMLElement', LIBXML_NONET) : false;
            $sitemapLinks = [];
            if ($sitemapXml !== false) {
                foreach ($sitemapXml->url as $urlNode) {
                    $sitemapLinks[] = (string) $urlNode->loc;
                }
            }
            $sitemapValid = $sitemapResponse->successful() && $sitemapXml !== false && count($sitemapLinks) === count(array_unique($sitemapLinks));
        } catch (Throwable) {
            $sitemapValid = false;
            $sitemapLinks = [];
        }

        try {
            $robotsResponse = Http::timeout(10)->get($robotsUrl);
            $robotsBody = $robotsResponse->successful() ? $robotsResponse->body() : '';
            $robotsValid = $robotsResponse->successful()
                && str_contains($robotsBody, 'Sitemap: '.self::ORIGIN.'/sitemap.xml')
                && (bool) preg_match('/^User-agent:\s*\*\s*\R(?:(?!User-agent:).)*Allow:\s*\//mi', $robotsBody);
        } catch (Throwable) {
            $robotsValid = false;
        }

        $pageResults = [];
        foreach ($candidates as $candidate) {
            try {
                $response = Http::timeout(12)->get($candidate['url']);
                $pageResults[$candidate['url']] = $response->successful() ? $response->body() : '';
                $candidatesByUrl[$candidate['url']] = $candidate;
                $problems = [];
                if ($response->status() !== 200) {
                    $problems[] = 'http_status_'.$response->status();
                }
                $canonical = $this->canonical($pageResults[$candidate['url']]);
                if ($canonical !== $candidate['url']) {
                    $problems[] = 'canonical_mismatch';
                }
                if ($this->hasNoindex($pageResults[$candidate['url']])) {
                    $problems[] = 'noindex_directive';
                }
                if (! in_array($candidate['url'], $sitemapLinks, true)) {
                    $problems[] = 'missing_from_sitemap';
                }
                $audit->items()->updateOrCreate(['url' => $candidate['url']], [
                    ...$candidate,
                    'http_status' => $response->status(),
                    'canonical_url' => $canonical,
                    'sitemap_included' => in_array($candidate['url'], $sitemapLinks, true),
                    'local_status' => $problems === [] ? 'eligible' : 'invalid',
                    'problems' => $problems,
                ]);
            } catch (Throwable $exception) {
                $pageResults[$candidate['url']] = '';
                $audit->items()->updateOrCreate(['url' => $candidate['url']], [
                    ...$candidate,
                    'http_status' => null,
                    'canonical_url' => null,
                    'sitemap_included' => in_array($candidate['url'], $sitemapLinks, true),
                    'local_status' => 'invalid',
                    'problems' => ['request_failed'],
                ]);
            }
        }

        $links = [];
        foreach ($pageResults as $html) {
            foreach ($this->links($html) as $url) {
                $links[$url] = true;
            }
        }
        foreach ($candidates as $candidate) {
            $found = isset($links[$candidate['url']]);
            if (! $found && isset($candidate['category_url'])) {
                $found = isset($links[$candidate['category_url']]);
                if ($found && isset($pageResults[$candidate['category_url']])) {
                    $found = isset($links[$candidate['url']]);
                }
            }
            $item = $audit->items()->where('url', $candidate['url'])->first();
            if ($item) {
                $item->update(['discovered' => $found]);
                if (! $found && $item->local_status === 'eligible') {
                    $item->update(['local_status' => 'invalid', 'problems' => [...($item->problems ?? []), 'not_linked_from_home_or_category']]);
                }
            }
        }

        $items = $audit->items()->get();
        $invalid = $items->where('local_status', 'invalid')->count();
        $audit->update([
            'status' => 'completed',
            'completed_at' => now(),
            'summary' => [
                'published_articles' => Article::where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now())->count(),
                'eligible_categories' => Category::whereHas('articles', fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now()))->count(),
                'locally_indexable' => $items->where('local_status', 'eligible')->count(),
                'sitemap_urls' => count(array_unique($sitemapLinks)),
                'sitemap_valid' => $sitemapValid,
                'robots_valid' => $robotsValid,
                'technical_problems' => $invalid + (int) ! $sitemapValid + (int) ! $robotsValid,
            ],
        ]);

        return $audit->fresh('items');
    }

    private function canonical(string $html): ?string
    {
        if ($html === '') return null;
        $document = new \DOMDocument();
        @$document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        foreach ($document->getElementsByTagName('link') as $node) {
            if (strtolower($node->getAttribute('rel')) === 'canonical') return $node->getAttribute('href');
        }
        return null;
    }

    private function hasNoindex(string $html): bool
    {
        if ($html === '') return false;
        $document = new \DOMDocument();
        @$document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        foreach ($document->getElementsByTagName('meta') as $node) {
            if (strtolower($node->getAttribute('name')) === 'robots' && Str::contains(strtolower($node->getAttribute('content')), 'noindex')) return true;
        }
        return false;
    }

    private function links(string $html): array
    {
        if ($html === '') return [];
        $document = new \DOMDocument();
        @$document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        $links = [];
        foreach ($document->getElementsByTagName('a') as $node) {
            $href = trim($node->getAttribute('href'));
            if (str_starts_with($href, '/')) $links[] = self::ORIGIN.$href;
            elseif (str_starts_with($href, self::ORIGIN.'/')) $links[] = $href;
        }
        return $links;
    }
}
