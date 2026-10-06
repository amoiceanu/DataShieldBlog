<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContactMessage;
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
        $this->get('/')->assertOk()->assertSee('Protejează-ți datele');
        $this->get('/articole')->assertOk()->assertSee($article->title);
        $this->get(route('articles.show', $article))->assertOk()->assertSee($article->title);
        $this->get('/articole/nu-exista')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/robots.txt')->assertOk()->assertSee('/sitemap.xml');
    }

    public function test_search_and_category_filter_work(): void
    {
        $this->get('/articole?q=phishing')->assertOk()->assertSee('Cum recunoști un e-mail de tip phishing');
        $this->get('/articole?categorie=phishing')->assertOk()->assertSee('Cum recunoști un e-mail de tip phishing')->assertDontSee('10 reguli simple ca să îți protejezi');
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
}
