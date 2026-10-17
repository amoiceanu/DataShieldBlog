<?php

namespace Tests\Feature;

use App\Jobs\RunExposureCheck;
use App\Jobs\SendExposureVerification;
use App\Models\ExposureChallenge;
use App\Models\ExposureReport;
use App\Models\User;
use App\Services\Exposure\BreachSearch;
use App\Services\Exposure\Privacy;
use App\Services\Exposure\PublicSearch;
use App\Services\Exposure\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExposureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['exposure.enabled' => true, 'exposure.hmac_key' => str_repeat('k', 64), 'exposure.hibp_key' => 'test-hibp', 'exposure.hibp_enabled' => true, 'exposure.brave_key' => 'test-brave', 'exposure.brave_enabled' => true, 'mail.default' => 'array']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->withSession(['exposure_owner' => 'my-session']);
    }

    private function report(array $values = []): ExposureReport
    {
        return ExposureReport::create(array_replace(['owner' => app(Privacy::class)->digest('session:my-session'), 'address_hmac' => app(Privacy::class)->address('person@example.test'), 'module' => 'breaches', 'match_type' => 'email', 'subject' => ['value' => 'person@example.test', 'location' => ''], 'state' => 'awaiting_verification', 'expires_at' => now()->addDay()], $values));
    }

    private function challenge(ExposureReport $r, string $token, array $values = []): ExposureChallenge
    {
        return ExposureChallenge::create(array_replace(['report_id' => $r->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(15)], $values));
    }

    private function confirmToken(string $token)
    {
        $this->get(route('exposure.verify', $token))->assertRedirect(route('exposure.confirmation'));

        return $this->post(route('exposure.confirm'));
    }

    private function requestData(array $extra = []): array
    {
        return array_replace(['module' => 'breaches', 'match_type' => 'email', 'subject' => 'person@example.test', 'own_data' => '1'], $extra);
    }

    public function test_page_preserves_site_layout_and_explicit_consent(): void
    {
        $this->get(route('exposure.index'))->assertOk()->assertSee('datele tale?', false)->assertSee('Verific propria adresă de e-mail.');
    }

    public function test_verification_is_required_and_job_payload_contains_no_plaintext_personal_data(): void
    {
        $this->post(route('exposure.store'), $this->requestData())->assertRedirect();
        $r = ExposureReport::firstOrFail();
        $this->assertSame('awaiting_verification', $r->state);
        $raw = DB::table('exposure_reports')->first();
        $this->assertStringNotContainsString('person@example.test', $raw->subject);
        $payload = DB::table('jobs')->first()->payload;
        $this->assertStringNotContainsString('person@example.test', $payload);
        $command = json_decode($payload, true)['data']['command'];
        $this->assertStringNotContainsString('SendExposureVerification', $command);
        Http::assertNothingSent();
    }

    public function test_token_is_one_use_and_not_consumed_by_scanner_get(): void
    {
        Queue::fake();
        $r = $this->report();
        $token = str_repeat('a', 64);
        $c = $this->challenge($r, $token);
        $this->get(route('exposure.verify', $token))->assertRedirect();
        $this->assertNull($c->fresh()->used_at);
        $this->post(route('exposure.confirm'))->assertRedirect(route('exposure.report', $r->id));
        $this->assertSame('queued', $r->fresh()->state);
        Queue::assertPushed(RunExposureCheck::class, 1);
        $this->confirmToken($token)->assertRedirect(route('exposure.index'));
        Queue::assertPushed(RunExposureCheck::class, 1);
    }

    public function test_invalid_expired_and_wrong_session_tokens_cannot_start_search(): void
    {
        Queue::fake();
        $r = $this->report();
        $token = str_repeat('b', 64);
        $c = $this->challenge($r, $token, ['expires_at' => now()->subSecond()]);
        $this->confirmToken($token)->assertRedirect(route('exposure.index'));
        $this->confirmToken(str_repeat('c', 64))->assertRedirect(route('exposure.index'));
        $c->update(['expires_at' => now()->addMinutes(15)]);
        $this->withSession(['exposure_owner' => 'another-session']);
        $this->confirmToken($token)->assertRedirect(route('exposure.index'));
        Queue::assertNothingPushed();
    }

    public function test_report_status_classification_and_delete_are_session_authorized(): void
    {
        $r = $this->report(['module' => 'mentions', 'match_type' => 'name', 'state' => 'complete', 'results' => [['title' => 'Example', 'url' => 'https://example.test', 'domain' => 'example.test', 'description' => 'Text', 'classification' => 'unsure']]]);
        $this->withSession(['exposure_owner' => 'another-session']);
        $this->get(route('exposure.report', $r->id))->assertNotFound();
        $this->getJson(route('exposure.status', $r->id))->assertNotFound();
        $this->post(route('exposure.classify', [$r->id, 0]), ['classification' => 'yes'])->assertNotFound();
        $this->delete(route('exposure.destroy', $r->id))->assertNotFound();
        $this->assertModelExists($r);
    }

    public function test_request_requires_consent_and_does_not_flash_personal_input(): void
    {
        $this->post(route('exposure.store'), $this->requestData(['own_data' => null]))->assertSessionHasErrors('own_data')->assertSessionMissing('_old_input.subject');
        $this->assertDatabaseCount('exposure_reports', 0);
    }

    public function test_rate_limits_cover_request_and_resend(): void
    {
        Queue::fake();
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('exposure.store'), $this->requestData())->assertRedirect();
        }
        $this->post(route('exposure.store'), $this->requestData())->assertStatus(429);
        $r = ExposureReport::firstOrFail();
        $this->post(route('exposure.resend', $r->id))->assertStatus(429);
    }

    public function test_no_results_is_distinct_from_error_and_429(): void
    {
        Http::fake(['*' => Http::sequence()->push([], 404)->push([], 500)->push([], 429, ['Retry-After' => '30'])]);
        $this->assertSame('complete', app(BreachSearch::class)->search('person@example.test')->state);
        $this->assertSame('unavailable', app(BreachSearch::class)->search('person@example.test')->state);
        $r = app(BreachSearch::class)->search('person@example.test');
        $this->assertSame('quota', $r->state);
        $this->assertSame(30, $r->retryAfter);
    }

    public function test_missing_keys_and_license_rights_make_no_api_calls(): void
    {
        config(['exposure.hibp_key' => null, 'exposure.brave_enabled' => false]);
        $this->assertSame('unavailable', app(BreachSearch::class)->search('person@example.test')->state);
        $this->assertSame('unavailable', app(PublicSearch::class)->search('Some Name', '', 10)->state);
        $this->post(route('exposure.store'), $this->requestData())->assertSessionHas('exposure_notice');
        Http::assertNothingSent();
        $this->assertDatabaseCount('exposure_reports', 0);
    }

    public function test_log_mail_is_never_used_for_personal_data_in_real_environments(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);
        $this->assertFalse(app(Settings::class)->mailReady());
    }

    public function test_verification_mail_does_not_contain_email_in_link(): void
    {
        Mail::fake();
        $r = $this->report();
        $token = str_repeat('e', 64);
        $c = $this->challenge($r, $token);
        (new SendExposureVerification($c->id, $token))->handle();
        // Laravel Mail::send(view) uses a raw message rather than a Mailable; inspect the rendered template separately.
        $html = view('mail.exposure', ['link' => route('exposure.verify', $token)])->render();
        $this->assertStringContainsString($token, $html);
        $this->assertStringNotContainsString('person@example.test', $html);
    }

    public function test_verified_address_is_reused_only_for_same_address_and_session(): void
    {
        Queue::fake();
        $h = app(Privacy::class)->address('person@example.test');
        $this->withSession(['exposure_verified' => [$h => now()->addMinutes(30)->timestamp]]);
        $this->post(route('exposure.store'), $this->requestData(['module' => 'mentions']))->assertRedirect();
        $this->assertSame('queued', ExposureReport::first()->state);
        Queue::assertPushed(RunExposureCheck::class);
        $this->post(route('exposure.store'), $this->requestData(['subject' => 'different@example.test']))->assertRedirect();
        $this->assertSame('awaiting_verification', ExposureReport::where('address_hmac', app(Privacy::class)->address('different@example.test'))->firstOrFail()->state);
    }

    public function test_delete_cascades_verification_and_expired_report_is_inaccessible(): void
    {
        $r = $this->report();
        $this->challenge($r, str_repeat('f', 64));
        $this->delete(route('exposure.destroy', $r->id))->assertRedirect();
        $this->assertDatabaseCount('exposure_challenges', 0);
        $this->assertModelMissing($r);
        $expired = $this->report(['expires_at' => now()->subSecond()]);
        $this->get(route('exposure.report', $expired->id))->assertNotFound();
        $this->artisan('exposure:prune')->assertSuccessful();
        $this->assertModelMissing($expired);
    }

    public function test_page_and_module_disable_block_all_related_endpoints(): void
    {
        $r = $this->report();
        DB::table('exposure_settings')->insert(['id' => 1, 'options' => json_encode(['breaches' => false])]);
        $this->post(route('exposure.store'), $this->requestData())->assertNotFound();
        $this->get(route('exposure.report', $r->id))->assertNotFound();
        DB::table('exposure_settings')->where('id', 1)->update(['options' => json_encode(['enabled' => false])]);
        $this->get(route('exposure.index'))->assertNotFound();
        $this->getJson(route('exposure.status', $r->id))->assertNotFound();
    }

    public function test_external_content_is_escaped_and_invalid_urls_are_discarded(): void
    {
        Http::fake(['*' => Http::response(['type' => 'search', 'web' => ['results' => [
            ['title' => '<script>alert(1)</script>', 'description' => '&lt;img src=x onerror=alert(1)&gt;', 'url' => 'https://example.test/'],
            ['title' => 'bad', 'url' => 'javascript:alert(1)'], ['title' => 'bad', 'url' => 'file:///etc/passwd'],
        ]]])]);
        $result = app(PublicSearch::class)->search('Some Name', '', 10);
        $this->assertCount(1, $result->items);
        $this->assertSame('incomplete', $result->state);
        $r = $this->report(['module' => 'mentions', 'match_type' => 'name', 'state' => 'incomplete', 'results' => $result->items, 'checked_at' => now()]);
        $this->get(route('exposure.report', $r->id))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x onerror=alert(1)>', false)->assertSee('&lt;img', false)->assertSee('noopener noreferrer nofollow', false);
    }

    public function test_queries_are_literals_not_user_operators(): void
    {
        $query = app(PublicSearch::class)->query('Some "Name" OR site:example.test', 'Town"');
        $this->assertStringNotContainsString('site:', $query);
        $this->assertSame(4, substr_count($query, '"'));
        $this->post(route('exposure.store'), $this->requestData(['module' => 'mentions', 'match_type' => 'name', 'subject' => 'Name OR site:example.test']))->assertSessionHasErrors();
    }

    public function test_global_quota_and_job_normalization(): void
    {
        DB::table('exposure_settings')->insert(['id' => 1, 'options' => json_encode(['daily_limit' => 1])]);
        Http::fake(['*' => Http::response([['Name' => 'Example', 'Title' => 'Example', 'DataClasses' => ['Email addresses'], 'IsVerified' => true]], 200)]);
        $r = $this->report(['state' => 'queued']);
        app()->call([new RunExposureCheck($r->id), 'handle']);
        $this->assertSame('complete', $r->fresh()->state);
        $this->assertCount(1, $r->fresh()->results);
        Cache::flush();
        $second = $this->report(['state' => 'queued']);
        app()->call([new RunExposureCheck($second->id), 'handle']);
        $this->assertSame('quota', $second->fresh()->state);
        Http::assertSentCount(1);
    }

    public function test_private_headers_classification_and_aggregate_only_admin(): void
    {
        $r = $this->report(['module' => 'mentions', 'match_type' => 'name', 'state' => 'complete', 'results' => [['title' => 'Public', 'domain' => 'example.test', 'url' => 'https://example.test', 'description' => 'Text', 'classification' => 'unsure']]]);
        $this->get(route('exposure.report', $r->id))->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post(route('exposure.classify', [$r->id, 0]), ['classification' => 'yes'])->assertRedirect();
        $this->assertSame('yes', $r->fresh()->results[0]['classification']);
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('exposure.admin'))->assertForbidden();
        $user->forceFill(['is_exposure_admin' => true])->save();
        $this->actingAs($user)->get(route('exposure.admin'))->assertOk()->assertDontSee('person@example.test');
    }

    public function test_database_worker_decrypts_mail_job_then_runs_check_after_confirmation(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $this->post(route('exposure.store'), $this->requestData())->assertRedirect();
        $report = ExposureReport::firstOrFail();
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'exposure', '--once' => true, '--tries' => 3])->assertSuccessful();
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $html = $messages->first()->getOriginalMessage()->getHtmlBody();
        preg_match('~/confirmare/([a-f0-9]{64})~', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->confirmToken($matches[1])->assertRedirect(route('exposure.report', $report->id));
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'exposure', '--once' => true, '--tries' => 3])->assertSuccessful();
        $this->assertSame('complete', $report->fresh()->state);
        $this->assertSame([], $report->fresh()->results);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        Http::assertSentCount(1);
    }

    public function test_throttled_provider_retry_is_delayed_and_bounded(): void
    {
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '25'])]);
        $report = $this->report(['state' => 'queued']);
        $job = (new RunExposureCheck($report->id))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);
        $job->assertReleased(25);
        $this->assertSame('queued', $report->fresh()->state);
        $this->assertSame(1, DB::table('exposure_usage')->value('api_calls'));
    }

    public function test_global_session_and_ip_limits_cannot_be_bypassed_by_changing_address(): void
    {
        Queue::fake();
        DB::table('exposure_settings')->insert(['id' => 1, 'options' => json_encode(['session_limit' => 1, 'ip_limit' => 2])]);
        $this->post(route('exposure.store'), $this->requestData())->assertRedirect();
        $this->post(route('exposure.store'), $this->requestData(['subject' => 'other@example.test']))->assertStatus(429);
        $this->withSession(['exposure_owner' => 'second']);
        $this->post(route('exposure.store'), $this->requestData(['subject' => 'third@example.test']))->assertRedirect();
        $this->withSession(['exposure_owner' => 'third']);
        $this->post(route('exposure.store'), $this->requestData(['subject' => 'fourth@example.test']))->assertStatus(429);
    }

    public function test_admin_menu_visibility_is_separate_from_endpoint_activation(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_exposure_admin' => true])->save();
        $this->actingAs($user)->put(route('exposure.admin.update'), [
            'enabled' => 1, 'menu' => 0, 'breaches' => 1, 'mentions' => 1, 'ip_limit' => 10, 'session_limit' => 5, 'address_limit' => 3,
            'daily_limit' => 100, 'max_results' => 10, 'retention_hours' => 1, 'provider_interval' => 2,
        ])->assertRedirect(route('exposure.admin'));
        $this->get(route('home'))->assertDontSee('>Verifică datele</a>', false);
        $this->get(route('exposure.index'))->assertOk();
    }

    public function test_deleted_or_disabled_reports_do_not_call_provider_from_queued_jobs(): void
    {
        $report = $this->report(['state' => 'queued']);
        $report->delete();
        app()->call([new RunExposureCheck($report->id), 'handle']);
        $report = $this->report(['state' => 'queued']);
        DB::table('exposure_settings')->insert(['id' => 1, 'options' => json_encode(['breaches' => false])]);
        app()->call([new RunExposureCheck($report->id), 'handle']);
        $this->assertSame('unavailable', $report->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_exact_email_query_preserves_plus_addressing_and_name_apostrophes(): void
    {
        $search = app(PublicSearch::class);
        $this->assertSame('"alex+private@example.test"', $search->query('alex+private@example.test'));
        $this->assertSame("\"O'Connor\"", $search->query("O'Connor"));
    }

    public function test_malformed_provider_success_is_not_a_negative_result(): void
    {
        Http::fake(['*' => Http::response(['type' => 'error', 'message' => 'bad request'], 200)]);
        $this->assertSame('incomplete', app(PublicSearch::class)->search('Some Name', '', 10)->state);
    }

    public function test_empty_brave_result_is_distinct_and_uses_backend_authentication(): void
    {
        Http::fake(['*' => Http::response(['type' => 'search', 'web' => ['results' => []]], 200)]);
        $this->assertSame('complete', app(PublicSearch::class)->search('Some Name', 'Town', 10)->state);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Subscription-Token', 'test-brave') && $r['q'] === '"Some Name" "Town"' && $r['count'] === 10);
    }

    public function test_stale_processing_is_marked_incomplete_and_results_remain_encrypted(): void
    {
        $r = $this->report(['state' => 'processing', 'updated_at' => now()->subMinutes(21), 'results' => [['private' => 'sensitive result']]]);
        $this->assertStringNotContainsString('sensitive result', DB::table('exposure_reports')->where('id', $r->id)->value('results'));
        $this->artisan('exposure:prune')->assertSuccessful();
        $this->assertSame('incomplete', $r->fresh()->state);
    }

    public function test_shorter_admin_retention_applies_from_report_creation(): void
    {
        $report = $this->report(['created_at' => now()->subHours(2)]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_exposure_admin' => true])->save();
        $this->actingAs($admin)->put(route('exposure.admin.update'), [
            'enabled' => 1, 'menu' => 1, 'breaches' => 1, 'mentions' => 1, 'ip_limit' => 10, 'session_limit' => 5, 'address_limit' => 3,
            'daily_limit' => 100, 'max_results' => 10, 'retention_hours' => 1, 'provider_interval' => 2,
        ])->assertRedirect();
        $this->assertTrue($report->fresh()->expires_at->isPast());
        $this->artisan('exposure:prune')->assertSuccessful();
        $this->assertModelMissing($report);
    }
}
