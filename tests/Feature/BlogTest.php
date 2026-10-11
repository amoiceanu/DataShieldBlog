<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_articles_and_article_detail_render(): void
    {
        $article = Article::firstOrFail();
        $this->get('/')
            ->assertOk()
            ->assertSee('Protejează-ți viața')
            ->assertSee('Securitate Digitală')
            ->assertSee('og:site_name', false)
            ->assertSee('https://securitatedigitala.ro')
            ->assertDontSee('Administrare');
        $this->get('/articole?q='.urlencode($article->title))->assertOk()->assertSee($article->title);
        $this->get(route('articles.show', $article))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Redacția Securitate Digitală');
        $this->get('/articole/nu-exista')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false)->assertSee('https://securitatedigitala.ro');
        $this->get('/robots.txt')->assertOk()->assertSee('https://securitatedigitala.ro/sitemap.xml');
    }

    public function test_search_and_category_filter_work(): void
    {
        $this->get('/articole?q=phishing')->assertOk()->assertSee('Cum recunoști un e-mail de tip phishing');
        $this->get('/articole?categorie=fraude-online')->assertOk()->assertSee('Cum recunoști un e-mail de tip phishing')->assertDontSee('10 reguli simple ca să îți protejezi');
    }

    public function test_catalog_import_creates_ten_sections_and_publishes_twenty_seven_articles_idempotently(): void
    {
        $original = Article::where('slug', '10-reguli-simple-protejare-date-personale-online')->firstOrFail();
        $originalContent = $original->content;
        $this->assertDatabaseCount('categories', 10);
        $this->assertDatabaseCount('articles', 30);
        $this->assertSame(30, Article::where('status', 'published')->count());
        $this->assertSame(27, Article::where('status', 'published')->whereNotIn('slug', [
            '10-reguli-simple-protejare-date-personale-online',
            'cum-recunosti-email-phishing',
            'date-personale-nu-posta-online',
        ])->whereNotNull('published_at')->whereNotNull('seo_title')->whereNotNull('meta_description')->whereNotNull('focus_keyword')->whereNotNull('secondary_keywords')->count());
        $this->assertSame(27, Article::where('status', 'published')->whereNotIn('slug', [
            '10-reguli-simple-protejare-date-personale-online',
            'cum-recunosti-email-phishing',
            'date-personale-nu-posta-online',
        ])->whereNotNull('seo_title')->distinct()->count('seo_title'));
        $this->assertDatabaseMissing('categories', ['slug' => 'phishing']);
        $this->assertDatabaseHas('articles', [
            'slug' => '10-reguli-simple-protejare-date-personale-online',
            'category_id' => Category::where('slug', 'date-personale')->value('id'),
        ]);

        foreach (Article::where('status', 'published')->whereNotIn('slug', [
            '10-reguli-simple-protejare-date-personale-online',
            'cum-recunosti-email-phishing',
            'date-personale-nu-posta-online',
        ])->get() as $catalogArticle) {
            preg_match_all('/[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*/u', $catalogArticle->excerpt, $excerptWords);
            $this->assertGreaterThanOrEqual(30, count($excerptWords[0]), $catalogArticle->slug.' excerpt is too short');
            $this->assertLessThanOrEqual(50, count($excerptWords[0]), $catalogArticle->slug.' excerpt is too long');
            $this->assertNotSame('', trim(strip_tags($catalogArticle->content)), $catalogArticle->slug.' has no article body');
            $this->assertMatchesRegularExpression('/<h2>Articole conexe<\/h2>/', $catalogArticle->content, $catalogArticle->slug.' has no related articles');
            $this->assertMatchesRegularExpression('/<h2>Surse și resurse utile<\/h2>/', $catalogArticle->content, $catalogArticle->slug.' has no sources');
            preg_match_all('/href="\/articole\/([^"?#]+)"/', $catalogArticle->content, $internalLinks);
            $this->assertNotEmpty($internalLinks[1], $catalogArticle->slug.' has no internal article links');
            foreach ($internalLinks[1] as $linkedSlug) {
                $this->assertDatabaseHas('articles', ['slug' => $linkedSlug]);
            }
            preg_match_all('/href="https:\/\//', $catalogArticle->content, $sourceLinks);
            $this->assertGreaterThanOrEqual(2, count($sourceLinks[0]), $catalogArticle->slug.' has fewer than two external sources');
            $this->assertLessThanOrEqual(4, count($sourceLinks[0]), $catalogArticle->slug.' has more than four external sources');
        }

        $article = Article::where('slug', 'cum-recunosti-sms-fals-fraude-mesaje')->firstOrFail();
        $article->update(['content' => '<p>Conținut editorial păstrat</p>']);
        $this->seed(\Database\Seeders\ContentCatalogSeeder::class);

        $this->assertDatabaseCount('categories', 10);
        $this->assertDatabaseCount('articles', 30);
        $this->assertSame('<p>Conținut editorial păstrat</p>', $article->fresh()->content);
        $this->assertSame('published', $article->fresh()->status);
        $this->assertSame($originalContent, $original->fresh()->content);
        $this->assertSame('published', $original->fresh()->status);

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.articole.show', $article))->assertOk()->assertSee($article->title);
        $this->get(route('admin.articole.edit', $article))->assertOk()->assertSee($article->title);
    }

    public function test_old_category_query_urls_redirect_permanently_to_renamed_slugs(): void
    {
        $this->get('/articole?categorie=phishing&q=mesaj')
            ->assertStatus(301)
            ->assertRedirect('/articole?categorie=fraude-online&q=mesaj');

        foreach ([
            'ghiduri' => 'ghiduri-practice',
            'parole' => 'parole-autentificare',
            'social-media' => 'retele-sociale',
            'securitate-online' => 'securitate-dispozitive',
        ] as $oldSlug => $newSlug) {
            $this->get('/articole?categorie='.$oldSlug)
                ->assertStatus(301)
                ->assertRedirect('/articole?categorie='.$newSlug);
        }
    }

    public function test_valid_contact_is_saved_and_privacy_acceptance_is_required(): void
    {
        $data = ['name' => 'Ana Exemplu', 'email' => 'ana@example.com', 'subject' => 'Întrebare', 'message' => 'Aș dori mai multe informații.', 'privacy_accepted' => '1', 'website' => ''];
        $this->post(route('contact.store'), $data)->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertNotNull(ContactMessage::first()->privacy_accepted_at);
        unset($data['privacy_accepted']);
        $this->from(route('contact'))->post(route('contact.store'), $data)->assertSessionHasErrors('privacy_accepted');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_invalid_contact_and_honeypot_are_not_saved(): void
    {
        $this->post(route('contact.store'), ['name' => '', 'email' => 'invalid'])->assertSessionHasErrors(['name', 'email']);
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Test', 'message' => 'Mesaj suficient de lung.', 'privacy_accepted' => '1', 'website' => 'spam.example'])->assertSessionHas('success');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_drafts_are_hidden_from_public_pages_and_sitemap(): void
    {
        $article = Article::firstOrFail();
        $article->update(['status' => 'draft']);

        $this->get('/')->assertDontSee($article->title);
        $this->get('/articole')->assertDontSee($article->title);
        $this->get(route('articles.show', $article))->assertNotFound();
        $catalogDraft = Article::where('slug', 'cum-recunosti-sms-fals-fraude-mesaje')->firstOrFail();
        $catalogDraft->update(['status' => 'draft', 'published_at' => null]);
        $this->get('/')->assertDontSee($catalogDraft->title);
        $this->get('/articole')->assertDontSee($catalogDraft->title);
        $this->get(route('articles.show', $catalogDraft))->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('https://securitatedigitala.ro/articole/'.$article->slug);
    }

    public function test_admin_routes_require_an_admin_account(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Autentificare');
        $this->get(route('admin.articole.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.login.store'), ['email' => 'reader@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');

        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get(route('admin.articole.index'))->assertForbidden();
    }

    public function test_admin_can_log_in_and_log_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'correct horse battery staple']);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'correct horse battery staple'])
            ->assertRedirect(route('admin.articole.index'));
        $this->get(route('admin.articole.index'))->assertOk()->assertSee('Articole');
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.articole.index'))->assertRedirect(route('admin.login'));

        $reader = User::factory()->create(['is_admin' => false]);
        $this->post(route('admin.login.store'), ['email' => $reader->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_admin_can_create_a_draft_then_publish_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::firstOrFail();
        $data = [
            'category_id' => $category->id,
            'title' => 'Articol pregătit pentru publicare',
            'slug' => '',
            'excerpt' => 'Descriere pentru un articol nou.',
            'content' => '<p>Conținutul articolului.</p>',
            'image' => '',
            'author' => 'Redacția Securitate Digitală',
            'reading_time' => 4,
            'status' => 'draft',
        ];

        $this->actingAs($admin)->get(route('admin.articole.index'))->assertOk();
        $this->get(route('admin.articole.create'))->assertOk()->assertSee('Articol nou');
        $this->post(route('admin.articole.store'), $data)->assertRedirect(route('admin.articole.index'));
        $article = Article::where('slug', 'articol-pregatit-pentru-publicare')->firstOrFail();
        $this->assertSame('draft', $article->status);
        $this->get(route('admin.articole.edit', $article))->assertOk()->assertSee($article->title);
        $this->get(route('articles.show', $article))->assertNotFound();

        $data['status'] = 'published';
        $data['published_at'] = now()->toDateString();
        $this->put(route('admin.articole.update', $article), $data)->assertRedirect(route('admin.articole.index'));
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'status' => 'published']);
        $this->get(route('articles.show', $article))->assertOk()->assertSee($article->title);
        $this->delete(route('admin.articole.destroy', $article))->assertRedirect();
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_admin_can_manage_sections_without_cascading_article_deletion(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::create(['name' => 'Secțiune de test', 'slug' => 'sectiune-de-test', 'description' => 'Descriere']);
        $article = Article::firstOrFail();

        $this->actingAs($admin)->get(route('admin.sectiuni.index'))->assertOk();
        $this->get(route('admin.sectiuni.create'))->assertOk()->assertSee('Secțiune nouă');
        $this->post(route('admin.sectiuni.store'), ['name' => 'Secțiune creată', 'slug' => '', 'description' => 'Nouă'])->assertRedirect(route('admin.sectiuni.index'));
        $this->assertDatabaseHas('categories', ['slug' => 'sectiune-creata']);
        $this->delete(route('admin.sectiuni.destroy', $article->category))->assertSessionHasErrors('category');
        $this->assertDatabaseHas('articles', ['id' => $article->id]);

        $this->put(route('admin.sectiuni.update', $category), ['name' => 'Secțiune actualizată', 'slug' => '', 'description' => 'Nou'])->assertRedirect(route('admin.sectiuni.index'));
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'sectiune-actualizata']);
        $this->delete(route('admin.sectiuni.destroy', $category))->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
