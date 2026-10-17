<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['is_exposure_admin' => true])->save();

        return $u;
    }

    private function payload(array $changes = []): array
    {
        $pages = [];
        foreach (config('site_pages') as $key => $page) {
            $pages[$key] = ['enabled' => 1];
            if (isset($page['route'])) {
                $pages[$key]['menu'] = 1;
            }
        }
        foreach ($changes as $key => $values) {
            $pages[$key] = array_replace($pages[$key], $values);
        }

        return ['pages' => $pages];
    }

    public function test_admin_section_requires_admin_and_stays_accessible_when_home_disabled(): void
    {
        $this->get('/admin/pagini-si-module')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get('/admin/pagini-si-module')->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['home' => ['enabled' => 0]]))->assertRedirect(route('admin.pages'));
        $this->get('/')->assertNotFound();
        $this->get(route('admin.pages'))->assertOk()->assertSee('Pagini și module');
        $this->get('/admin')->assertRedirect('/admin/pagini-si-module');
    }

    public function test_hidden_page_is_accessible_but_removed_from_header_and_footer(): void
    {
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['about' => ['menu' => 0]]))->assertRedirect();
        $this->get('/despre')->assertOk();
        $this->get('/')->assertDontSee('href="'.route('about').'"', false);
        $this->get('/sitemap.xml')->assertSee(route('about'));
    }

    public function test_disabled_page_blocks_get_and_post_and_is_removed_from_sitemap(): void
    {
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['contact' => ['enabled' => 0], 'privacy' => ['enabled' => 0]]))->assertRedirect();
        $this->get('/contact')->assertNotFound();
        $this->post('/contact', [])->assertNotFound();
        $this->get('/politica-de-confidentialitate')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('<loc>'.route('contact').'</loc>', false)->assertDontSee('<loc>'.route('privacy').'</loc>', false);
    }

    public function test_disabling_articles_blocks_detail_search_and_guides_and_hides_home_sections(): void
    {
        $this->seed();
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['articles' => ['enabled' => 0]]))->assertRedirect();
        $this->get('/articole')->assertNotFound();
        $this->get('/articole?categorie=ghiduri')->assertNotFound();
        $article = Article::first();
        $this->get(route('articles.show', $article))->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('Ultimele articole')->assertDontSee('Citește ghidurile');
        $this->get('/sitemap.xml')->assertDontSee('<loc>'.route('articles.show', $article).'</loc>', false);
    }

    public function test_guides_can_be_disabled_without_disabling_articles(): void
    {
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['guides' => ['enabled' => 0]]));
        $this->get('/articole')->assertOk();
        $this->get('/articole?categorie=ghiduri')->assertNotFound();
        $this->assertFalse(app(SitePages::class)->enabled('guides'));
    }

    public function test_exposure_controls_share_settings_and_preserve_existing_limits(): void
    {
        DB::table('exposure_settings')->insert(['id' => 1, 'options' => json_encode(['daily_limit' => 17, 'retention_hours' => 4])]);
        $this->actingAs($this->admin())->put(route('admin.pages.update'), $this->payload(['exposure' => ['enabled' => 0], 'mentions' => ['enabled' => 0]]))->assertRedirect();
        $options = json_decode(DB::table('exposure_settings')->value('options'), true);
        $this->assertSame(17, $options['daily_limit']);
        $this->assertSame(4, $options['retention_hours']);
        $this->assertFalse($options['enabled']);
        $this->assertFalse($options['mentions']);
        $this->get('/verificare-date-personale')->assertNotFound();
        $this->get('/verificare-date-personale/raport/unknown/status')->assertNotFound();
        $this->get(route('exposure.admin'))->assertOk();
    }

    public function test_invalid_or_incomplete_settings_are_rejected_without_partial_writes(): void
    {
        $this->actingAs($this->admin())->put(route('admin.pages.update'), ['pages' => ['home' => ['enabled' => 0, 'menu' => 1]]])->assertSessionHasErrors();
        $this->assertDatabaseCount('site_page_settings', 0);
        $this->assertDatabaseCount('exposure_settings', 0);
        $this->assertTrue(app(SitePages::class)->enabled('home'));
    }
}
