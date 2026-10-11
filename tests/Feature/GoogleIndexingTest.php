<?php

namespace Tests\Feature;

use App\Jobs\RunIndexabilityAudit;
use App\Models\Article;
use App\Models\Category;
use App\Models\GoogleSearchConsoleConnection;
use App\Models\IndexabilityAudit;
use App\Models\IndexabilityAuditItem;
use App\Models\User;
use App\Services\IndexabilityAuditService;
use App\Services\PublicSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleIndexingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_sitemap_has_homepage_eligible_categories_and_published_articles_only(): void
    {
        $draft = Article::firstOrFail();
        $draft->update(['status' => 'draft']);
        $this->assertSame(30, Article::count());
        $this->assertSame(29, Article::where('status', 'published')->count());
        $this->assertSame(29, Article::where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now())->count(), 'today='.now()->toDateTimeString().' first='.Article::where('status', 'published')->first()->published_at.' raw='.Article::where('status', 'published')->value('published_at'));
        $this->assertSame(45, app(PublicSitemap::class)->entries()->count());

        $response = $this->get('/sitemap.xml', ['User-Agent' => 'Googlebot'])->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertFalse($response->headers->has('Set-Cookie'), 'Sitemap GET must not start a cookie session.');
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $locations = collect($xml->xpath('//*[local-name()="url"]'))->map(fn ($node) => (string) $node->loc);
        $this->assertSame(45, $locations->count());
        $this->assertSame($locations->count(), $locations->unique()->count());
        $this->assertContains('https://securitatedigitala.ro/', $locations->all());
        foreach (['/articole', '/despre', '/contact', '/politica-de-confidentialitate', '/politica-cookies'] as $path) {
            $this->assertContains('https://securitatedigitala.ro'.$path, $locations->all());
        }
        $categoryUrl = 'https://securitatedigitala.ro'.route('sections.show', Category::whereHas('articles', fn ($query) => $query->where('status', 'published'))->firstOrFail()->slug, false);
        $this->assertContains($categoryUrl, $locations->all());
        $this->assertNotContains('https://securitatedigitala.ro'.route('articles.show', $draft->slug, false), $locations->all());
        $this->assertFalse($locations->contains(fn ($url) => str_contains((string) $url, '/admin')));
    }

    public function test_public_category_page_has_its_own_canonical_and_public_articles(): void
    {
        $category = Category::whereHas('articles', fn ($query) => $query->where('status', 'published'))->firstOrFail();
        $article = $category->articles()->where('status', 'published')->firstOrFail();

        $this->get(route('sections.show', $category->slug))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee($article->title)
            ->assertSee('<link rel="canonical" href="https://securitatedigitala.ro'.route('sections.show', $category->slug, absolute: false).'">', false)
            ->assertDontSee('noindex', false);
    }

    public function test_each_indexable_category_has_useful_content_and_is_listed_in_the_sitemap(): void
    {
        $categories = Category::query()
            ->whereHas('articles', fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now()))
            ->with(['articles' => fn ($query) => $query->where('status', 'published')->whereNotNull('published_at')->whereDate('published_at', '<=', now())->latest('published_at')])
            ->get();
        $this->assertCount(10, $categories);

        $response = $this->get('/sitemap.xml');
        $xml = simplexml_load_string($response->getContent());
        $locations = collect($xml->xpath('//*[local-name()="url"]'))->map(fn ($node) => (string) $node->loc);

        foreach ($categories as $category) {
            $canonical = 'https://securitatedigitala.ro'.route('sections.show', $category->slug, absolute: false);
            $this->assertContains($canonical, $locations->all());
            $this->get(route('sections.show', $category->slug))
                ->assertOk()
                ->assertSee($category->name)
                ->assertSee($category->articles->first()->title)
                ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
                ->assertDontSee('noindex', false);
        }
    }

    public function test_robots_txt_allows_public_crawling_and_advertises_the_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Allow: /')
            ->assertSee('Sitemap: https://securitatedigitala.ro/sitemap.xml');

        $response = $this->get('/robots.txt', ['User-Agent' => 'Googlebot'])->assertOk();
        $this->assertFalse($response->headers->has('Set-Cookie'), 'robots.txt GET must not start a cookie session.');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertSame("User-agent: *\nAllow: /\n\nSitemap: https://securitatedigitala.ro/sitemap.xml\n", $response->getContent());
    }

    public function test_www_host_redirects_to_the_canonical_domain_and_query(): void
    {
        $this->get('https://www.securitatedigitala.ro/articole?q=phishing')
            ->assertStatus(301)
            ->assertRedirect('https://securitatedigitala.ro/articole?q=phishing');
    }

    public function test_indexing_dashboard_is_protected_by_admin_authentication(): void
    {
        $this->get(route('admin.google-indexing.index'))->assertRedirect(route('admin.login'));
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.google-indexing.index'))->assertOk()->assertSee('Google Indexing')->assertSee('Stare Google')->assertSee('Necunoscută');
    }

    public function test_admin_can_queue_a_local_audit(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.audit'))->assertRedirect();

        Queue::assertPushed(RunIndexabilityAudit::class);
        $this->assertDatabaseHas('indexability_audits', ['status' => 'queued']);
    }

    public function test_local_audit_records_live_http_canonical_discovery_and_sitemap_results(): void
    {
        $urls = app(PublicSitemap::class)->entries()->pluck('loc')->all();
        $xml = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.collect($urls)->map(fn ($url) => '<url><loc>'.$url.'</loc></url>')->implode('').'</urlset>';
        Http::fake(function ($request) use ($xml, $urls) {
            if ($request->url() === 'https://securitatedigitala.ro/sitemap.xml') return Http::response($xml, 200, ['Content-Type' => 'application/xml']);
            if ($request->url() === 'https://securitatedigitala.ro/robots.txt') return Http::response("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: https://securitatedigitala.ro/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']);
            $url = $request->url();
            $links = collect($urls)->map(fn ($link) => '<a href="'.$link.'">legătură</a>')->implode('');

            return Http::response('<html><head><link rel="canonical" href="'.$url.'"></head><body>'.$links.'</body></html>');
        });
        $audit = app(IndexabilityAuditService::class)->run(IndexabilityAudit::create(['status' => 'queued']));

        $this->assertSame('completed', $audit->status);
        $this->assertSame(41, $audit->items->count());
        $this->assertSame(41, $audit->items->where('local_status', 'eligible')->count());
        $this->assertSame(41, $audit->items->where('discovered', true)->count());
        $this->assertSame(0, $audit->summary['technical_problems']);
    }

    public function test_artisan_indexability_command_creates_a_completed_live_audit(): void
    {
        $urls = app(PublicSitemap::class)->entries()->pluck('loc')->all();
        $xml = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.collect($urls)->map(fn ($url) => '<url><loc>'.$url.'</loc></url>')->implode('').'</urlset>';
        Http::fake(function ($request) use ($xml, $urls) {
            if ($request->url() === 'https://securitatedigitala.ro/sitemap.xml') return Http::response($xml, 200, ['Content-Type' => 'application/xml']);
            if ($request->url() === 'https://securitatedigitala.ro/robots.txt') return Http::response("User-agent: *\nAllow: /\nSitemap: https://securitatedigitala.ro/sitemap.xml\n", 200, ['Content-Type' => 'text/plain']);
            $links = collect($urls)->map(fn ($link) => '<a href="'.$link.'">link</a>')->implode('');

            return Http::response('<link rel="canonical" href="'.$request->url().'">'.$links);
        });

        $exitCode = Artisan::call('seo:check-indexability');

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertDatabaseHas('indexability_audits', ['status' => 'completed']);
    }

    public function test_google_oauth_configuration_is_required_before_connecting(): void
    {
        config(['services.google_search_console.client_id' => null, 'services.google_search_console.client_secret' => null]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.connect'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('google_search_console_connections', 0);
    }

    public function test_oauth_connect_redirects_with_offline_access_and_state(): void
    {
        config(['services.google_search_console.client_id' => 'client-id', 'services.google_search_console.client_secret' => 'client-secret', 'services.google_search_console.redirect_uri' => 'https://securitatedigitala.ro/admin/google-indexing/callback']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.google-indexing.connect'))->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('access_type=offline', $location);
        $this->assertNotEmpty(session('google_search_console_state'));
    }

    public function test_oauth_callback_stores_encrypted_tokens_after_google_confirms_the_property(): void
    {
        config(['services.google_search_console.client_id' => 'client-id', 'services.google_search_console.client_secret' => 'client-secret', 'services.google_search_console.redirect_uri' => 'https://securitatedigitala.ro/admin/google-indexing/callback']);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-token-value', 'refresh_token' => 'refresh-token-value', 'expires_in' => 3600, 'scope' => 'https://www.googleapis.com/auth/webmasters']),
            'https://www.googleapis.com/webmasters/v3/sites' => Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:securitatedigitala.ro']]]),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $state = 'a-secure-state-from-session';

        $this->actingAs($admin)->withSession(['google_search_console_state' => $state])
            ->get(route('admin.google-indexing.callback', ['code' => 'authorization-code', 'state' => $state]))
            ->assertRedirect(route('admin.google-indexing.index'))
            ->assertSessionHas('success');

        $connection = GoogleSearchConsoleConnection::firstOrFail();
        $this->assertSame('access-token-value', $connection->access_token);
        $this->assertSame('refresh-token-value', $connection->refresh_token);
        $this->assertNotSame('access-token-value', $connection->getRawOriginal('access_token'));
    }

    public function test_sitemap_validation_accepts_unique_canonical_xml_and_reports_invalid_xml(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Http::fake(['https://securitatedigitala.ro/sitemap.xml' => Http::sequence()
            ->push('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://securitatedigitala.ro/</loc></url></urlset>', 200, ['Content-Type' => 'application/xml'])
            ->push('<html>not XML sitemap</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->actingAs($admin)->post(route('admin.google-indexing.validate-sitemap'))->assertRedirect()->assertSessionHas('sitemap_validation.count', 1);
        $this->post(route('admin.google-indexing.validate-sitemap'))->assertSessionHasErrors('sitemap');
    }

    public function test_refresh_google_data_stores_sitemap_and_cached_search_analytics(): void
    {
        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/sitemaps' => Http::response(['sitemap' => [['path' => 'https://securitatedigitala.ro/sitemap.xml', 'lastSubmitted' => '2026-10-11T10:00:00Z']]]),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response(['rows' => [['clicks' => 12, 'impressions' => 120, 'ctr' => 0.1, 'position' => 8.2]]]),
        ]);
        GoogleSearchConsoleConnection::create(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'property' => 'sc-domain:securitatedigitala.ro']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.refresh'))->assertRedirect()->assertSessionHas('success');

        $connection = GoogleSearchConsoleConnection::firstOrFail();
        $this->assertSame(12, $connection->performance_data['performance']['clicks']);
        $this->assertSame('https://securitatedigitala.ro/sitemap.xml', $connection->sitemap_status['path']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/searchAnalytics/query'));
    }

    public function test_admin_can_disconnect_google_and_delete_saved_tokens(): void
    {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response('', 200)]);
        GoogleSearchConsoleConnection::create(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'property' => 'sc-domain:securitatedigitala.ro']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->delete(route('admin.google-indexing.disconnect'))->assertRedirect();

        $this->assertDatabaseCount('google_search_console_connections', 0);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke');
    }

    public function test_authorized_google_inspection_is_saved_using_the_actual_api_verdict(): void
    {
        Http::fake(['https://searchconsole.googleapis.com/v1/urlInspection/index:inspect' => Http::response([
            'inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed']],
        ])]);
        $connection = GoogleSearchConsoleConnection::create(['access_token' => 'encrypted-access-token', 'refresh_token' => 'encrypted-refresh-token', 'expires_at' => now()->addHour(), 'property' => 'sc-domain:securitatedigitala.ro']);
        $audit = IndexabilityAudit::create(['status' => 'completed']);
        $item = IndexabilityAuditItem::create(['audit_id' => $audit->id, 'title' => 'Ghid public', 'url' => 'https://securitatedigitala.ro/articole/ghid-public', 'type' => 'Article', 'local_status' => 'eligible']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.inspect', $item))->assertRedirect()->assertSessionHas('success');

        $this->assertSame('indexed', $item->fresh()->google_status);
        $this->assertSame('PASS', $item->fresh()->google_result['indexStatusResult']['verdict']);
        $this->assertNotSame('encrypted-access-token', $connection->getRawOriginal('access_token'));
    }

    public function test_google_sitemap_submission_is_recorded_only_after_google_success(): void
    {
        Http::fake(function ($request) {
            if ($request->method() === 'GET' && $request->url() === 'https://www.googleapis.com/webmasters/v3/sites') {
                return Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:securitatedigitala.ro']]]);
            }
            if ($request->method() === 'GET' && $request->url() === 'https://securitatedigitala.ro/sitemap.xml') {
                return Http::response('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://securitatedigitala.ro/</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']);
            }
            if ($request->method() === 'PUT') return Http::response('', 204);

            return Http::response([], 404);
        });
        GoogleSearchConsoleConnection::create(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'property' => 'sc-domain:securitatedigitala.ro']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.sitemap'))->assertRedirect()->assertSessionHas('success');

        $this->assertNotNull(GoogleSearchConsoleConnection::first()->last_sitemap_submission_at);
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/sitemaps/'));
    }

    public function test_unchanged_sitemap_is_not_submitted_twice(): void
    {
        $sitemap = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://securitatedigitala.ro/</loc></url></urlset>';
        Http::fake(function ($request) use ($sitemap) {
            if ($request->url() === 'https://www.googleapis.com/webmasters/v3/sites') return Http::response(['siteEntry' => [['siteUrl' => 'sc-domain:securitatedigitala.ro']]]);
            if ($request->url() === 'https://securitatedigitala.ro/sitemap.xml') return Http::response($sitemap, 200, ['Content-Type' => 'application/xml']);
            return Http::response('', 204);
        });
        GoogleSearchConsoleConnection::create(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour(), 'property' => 'sc-domain:securitatedigitala.ro', 'last_sitemap_hash' => hash('sha256', $sitemap), 'last_sitemap_submission_at' => now()->subHour()]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.google-indexing.sitemap'))->assertRedirect()->assertSessionHasErrors('sitemap');

        Http::assertNotSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/sitemaps/'));
    }

    public function test_url_export_contains_only_locally_eligible_urls(): void
    {
        $audit = IndexabilityAudit::create(['status' => 'completed']);
        IndexabilityAuditItem::create(['audit_id' => $audit->id, 'title' => 'Eligibil', 'url' => 'https://securitatedigitala.ro/eligibil', 'canonical_url' => 'https://securitatedigitala.ro/eligibil', 'type' => 'Article', 'http_status' => 200, 'local_status' => 'eligible']);
        IndexabilityAuditItem::create(['audit_id' => $audit->id, 'title' => 'Problematic', 'url' => 'https://securitatedigitala.ro/problematic', 'type' => 'Article', 'http_status' => 404, 'local_status' => 'invalid']);
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.google-indexing.export'))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('eligibil', $content);
        $this->assertStringNotContainsString('problematic', $content);
    }
}
