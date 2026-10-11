<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunIndexabilityAudit;
use App\Models\GoogleSearchConsoleConnection as Connection;
use App\Models\IndexabilityAudit;
use App\Models\IndexabilityAuditItem;
use App\Services\GoogleSearchConsole;
use App\Services\IndexabilityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GoogleIndexingController extends Controller
{
    public function index(IndexabilityAuditService $auditService, GoogleSearchConsole $google): View
    {
        $audit = IndexabilityAudit::with('items')->latest('id')->first();
        $items = $audit?->items ?? collect($auditService->candidates())->map(fn ($row) => new IndexabilityAuditItem([
            ...$row,
            'http_status' => null,
            'canonical_url' => null,
            'sitemap_included' => false,
            'discovered' => false,
            'local_status' => 'not_checked',
            'google_status' => null,
            'problems' => [],
        ]));
        $connection = Connection::first();
        $googleError = null;
        $propertyAvailable = false;
        $sitemapStatus = null;
        if ($connection) {
            try {
                $propertyAvailable = collect($google->siteProperties())->contains(fn ($site) => ($site['siteUrl'] ?? null) === $connection->property);
                if ($propertyAvailable) $sitemapStatus = $google->sitemapStatus($connection);
            } catch (Throwable $exception) {
                $googleError = $exception->getMessage();
            }
        }

        $summary = $audit?->summary ?? [
            'published_articles' => collect($items)->where('type', 'Article')->count(),
            'eligible_categories' => collect($items)->where('type', 'Category')->count(),
            'locally_indexable' => null,
            'sitemap_urls' => null,
            'technical_problems' => null,
            'sitemap_valid' => null,
            'robots_valid' => null,
        ];

        return view('admin.google-indexing.index', compact('audit', 'connection', 'googleError', 'items', 'propertyAvailable', 'sitemapStatus', 'summary', 'google'));
    }

    public function connect(Request $request, GoogleSearchConsole $google): RedirectResponse
    {
        if (! $google->configured()) {
            return back()->withErrors(['google' => 'Configurează GOOGLE_SEARCH_CONSOLE_CLIENT_ID, GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET și GOOGLE_SEARCH_CONSOLE_REDIRECT_URI înainte de conectare.']);
        }

        $state = Str::random(48);
        $request->session()->put('google_search_console_state', $state);

        return redirect()->away($google->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleSearchConsole $google): RedirectResponse
    {
        if ($request->filled('error')) return redirect()->route('admin.google-indexing.index')->withErrors(['google' => 'Conexiunea Google a fost anulată.']);

        $state = (string) $request->session()->pull('google_search_console_state');
        if (! $state || ! hash_equals($state, (string) $request->query('state'))) {
            return redirect()->route('admin.google-indexing.index')->withErrors(['google' => 'Răspunsul OAuth nu a trecut verificarea de securitate. Încearcă din nou.']);
        }

        try {
            $connection = $google->exchangeCode((string) $request->query('code'));
            $available = collect($google->siteProperties())->contains(fn ($site) => ($site['siteUrl'] ?? null) === $connection->property);
            if (! $available) {
                return redirect()->route('admin.google-indexing.index')->withErrors(['google' => 'Contul Google este conectat, dar nu are acces la proprietatea de domeniu sc-domain:securitatedigitala.ro. Verifică proprietatea și permisiunile în Search Console.']);
            }
        } catch (Throwable $exception) {
            return redirect()->route('admin.google-indexing.index')->withErrors(['google' => $exception->getMessage()]);
        }

        return redirect()->route('admin.google-indexing.index')->with('success', 'Search Console este conectat, iar proprietatea de domeniu este disponibilă.');
    }

    public function disconnect(GoogleSearchConsole $google): RedirectResponse
    {
        $connection = Connection::first();
        if ($connection) $google->revoke($connection);

        return back()->with('success', 'Conexiunea Search Console a fost eliminată.');
    }

    public function validateSitemap(): RedirectResponse
    {
        try {
            $response = Http::timeout(15)->get(config('services.google_search_console.sitemap'));
            $xml = $response->successful() ? @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NONET) : false;
            if (! $response->successful() || $xml === false || $xml->getName() !== 'urlset') {
                return back()->withErrors(['sitemap' => 'Sitemap-ul nu a returnat un document XML urlset valid (HTTP '.$response->status().').']);
            }
            $urls = collect($xml->url)->map(fn ($node) => (string) $node->loc);
            if ($urls->count() !== $urls->unique()->count()) return back()->withErrors(['sitemap' => 'Sitemap-ul conține URL-uri duplicate.']);
            if ($urls->contains(fn ($url) => ! str_starts_with($url, 'https://securitatedigitala.ro/'))) return back()->withErrors(['sitemap' => 'Sitemap-ul conține un URL în afara domeniului canonic HTTPS.']);
        } catch (Throwable) {
            return back()->withErrors(['sitemap' => 'Sitemap-ul nu a putut fi validat prin HTTP. Verifică logurile aplicației.']);
        }

        return back()->with('sitemap_validation', ['count' => $urls->count(), 'validated_at' => now()->toIso8601String()]);
    }

    public function submitSitemap(GoogleSearchConsole $google): RedirectResponse
    {
        $connection = Connection::first();
        if (! $connection) return back()->withErrors(['google' => 'Conectează mai întâi un cont Search Console autorizat.']);

        try {
            $available = collect($google->siteProperties())->contains(fn ($site) => ($site['siteUrl'] ?? null) === $connection->property);
            if (! $available) return back()->withErrors(['google' => 'Proprietatea de domeniu nu este disponibilă pentru contul conectat.']);
            $sitemap = Http::timeout(15)->get(config('services.google_search_console.sitemap'));
            if (! $sitemap->successful() || @simplexml_load_string($sitemap->body(), 'SimpleXMLElement', LIBXML_NONET) === false) {
                return back()->withErrors(['sitemap' => 'Sitemap-ul public nu este disponibil sau XML-ul nu este valid.']);
            }
            $contentHash = hash('sha256', $sitemap->body());
            if ($connection->last_sitemap_hash && hash_equals($connection->last_sitemap_hash, $contentHash)) {
                return back()->withErrors(['sitemap' => 'Sitemap-ul nu s-a schimbat de la ultima trimitere confirmată; nu a fost retrimis.']);
            }
            $google->submitSitemap($connection, $contentHash);
        } catch (Throwable $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        }

        return back()->with('success', 'Google Search Console a confirmat trimiterea sitemap-ului. Trimiterea este doar un semnal pentru Google și nu garantează indexarea.');
    }

    public function refreshGoogleData(GoogleSearchConsole $google): RedirectResponse
    {
        $connection = Connection::first();
        if (! $connection) return back()->withErrors(['google' => 'Conectează mai întâi un cont Search Console autorizat.']);

        try {
            $data = $google->refreshData($connection);
        } catch (Throwable $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        }

        return back()->with('success', 'Datele Search Console au fost actualizate pentru perioada '.$data['period']['from'].' – '.$data['period']['to'].'.');
    }

    public function inspect(IndexabilityAuditItem $auditItem, GoogleSearchConsole $google): RedirectResponse
    {
        $connection = Connection::first();
        if (! $connection) return back()->withErrors(['google' => 'Conectează mai întâi Search Console pentru a verifica starea reală din indexul Google.']);

        try {
            $result = $google->inspect($connection, $auditItem->url);
            $index = $result['indexStatusResult'] ?? [];
            $verdict = $index['verdict'] ?? null;
            $status = match ($verdict) {
                'PASS' => 'indexed',
                'FAIL' => 'not_indexed',
                default => 'unknown',
            };
            $auditItem->update(['google_status' => $status, 'google_result' => $result]);
        } catch (Throwable $exception) {
            return back()->withErrors(['google' => $exception->getMessage()]);
        }

        return back()->with('success', $status === 'unknown' ? 'Google a returnat un rezultat de inspecție fără verdict PASS sau FAIL; starea rămâne necunoscută.' : 'Rezultatul URL Inspection primit de la Google a fost salvat.');
    }

    public function audit(): RedirectResponse
    {
        $existing = IndexabilityAudit::whereIn('status', ['queued', 'running'])->latest('id')->first();
        if ($existing) return back()->with('success', 'Auditul #'.$existing->id.' este deja '.$existing->status.'.');

        $audit = IndexabilityAudit::create(['status' => 'queued']);
        RunIndexabilityAudit::dispatch($audit->id);

        return back()->with('success', 'Auditul de indexabilitate #'.$audit->id.' a fost pornit. Actualizează pagina pentru a vedea progresul.');
    }

    public function export(): StreamedResponse|RedirectResponse
    {
        $audit = IndexabilityAudit::with('items')->where('status', 'completed')->latest('id')->first();
        if (! $audit) return redirect()->route('admin.google-indexing.index')->withErrors(['audit' => 'Rulează mai întâi un audit ca să exporți doar URL-urile confirmate local ca indexabile.']);

        return response()->streamDownload(function () use ($audit): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Titlu', 'URL canonic', 'Tip', 'HTTP', 'Sitemap', 'Stare locală', 'Stare Google', 'Actualizat'], ';');
            foreach ($audit->items->where('local_status', 'eligible') as $item) {
                $cells = [$item->title, $item->canonical_url, $item->type, $item->http_status, $item->sitemap_included ? 'Da' : 'Nu', 'Indexabil local', $item->google_status ?? 'Necunoscut', $item->content_updated_at?->toIso8601String()];
                $cells = array_map(fn ($cell) => is_string($cell) && preg_match('/^[=+@-]/', $cell) ? "\t".$cell : $cell, $cells);
                fputcsv($output, $cells, ';');
            }
            fclose($output);
        }, 'securitate-digitala-url-uri-indexabile.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
