<?php

namespace Tests\Behat;

use Behat\Behat\Context\Context;
use Illuminate\Testing\TestResponse;

class FeatureContext implements Context
{
    private GherkinLaravelTestCase $test;

    private ?TestResponse $response = null;

    /** @BeforeScenario */
    public function bootApplication(): void
    {
        $this->test = new GherkinLaravelTestCase('gherkin scenario');
        $this->test->startScenario();
    }

    /** @AfterScenario */
    public function closeApplication(): void
    {
        $this->test->stopScenario();
    }

    /** @When I open the homepage */
    public function openHomepage(): void
    {
        $this->response = $this->test->get('/');
    }

    /** @When I search articles for :query */
    public function searchArticles(string $query): void
    {
        $this->response = $this->test->get('/articole?q='.urlencode($query));
    }

    /** @When I filter articles by category :slug */
    public function filterArticlesByCategory(string $slug): void
    {
        $this->response = $this->test->get('/articole?categorie='.urlencode($slug));
    }

    /** @When I open article :slug */
    public function openArticle(string $slug): void
    {
        $this->response = $this->test->get('/articole/'.$slug);
    }

    /** @When I submit a valid contact message with privacy consent */
    public function submitContactWithConsent(): void
    {
        $this->submitContact(['privacy_accepted' => '1']);
    }

    /** @When I submit a valid contact message without privacy consent */
    public function submitContactWithoutConsent(): void
    {
        $this->submitContact([]);
    }

    /** @Then the response status should be :status */
    public function responseStatusShouldBe(int $status): void
    {
        $this->ensure(
            $this->currentResponse()->getStatusCode() === $status,
            "Expected HTTP {$status}, received {$this->currentResponse()->getStatusCode()}."
        );
    }

    /** @Then the page should contain :text */
    public function pageShouldContain(string $text): void
    {
        $this->ensure(
            str_contains($this->currentResponse()->getContent(), $text),
            "Expected the page to contain: {$text}"
        );
    }

    /** @Then the page should not contain :text */
    public function pageShouldNotContain(string $text): void
    {
        $this->ensure(
            ! str_contains($this->currentResponse()->getContent(), $text),
            "Expected the page not to contain: {$text}"
        );
    }

    /** @Then the response should redirect */
    public function responseShouldRedirect(): void
    {
        $this->ensure($this->currentResponse()->isRedirect(), 'Expected the response to redirect.');
    }

    /** @Then the success message should be shown */
    public function successMessageShouldBeShown(): void
    {
        $this->ensure(
            $this->test->sessionHas('success', 'Mesajul tău a fost trimis. Îți mulțumim!'),
            'Expected the contact success flash message.'
        );
    }

    /** @Then the contact message should be stored */
    public function contactMessageShouldBeStored(): void
    {
        $this->ensure(
            $this->test->hasContactMessage('gherkin@example.com', 'Întrebare din scenariu'),
            'Expected the contact message to be saved.'
        );
    }

    /** @Then the response should contain a privacy consent validation error */
    public function privacyConsentValidationErrorShouldBeShown(): void
    {
        $this->ensure(
            $this->test->sessionHasValidationError('privacy_accepted'),
            'Expected a validation error for privacy consent.'
        );
    }

    /** @Then no contact message should be stored */
    public function noContactMessageShouldBeStored(): void
    {
        $this->ensure($this->test->contactMessageCount() === 0, 'Expected no contact messages to be saved.');
    }

    private function submitContact(array $extra): void
    {
        $this->response = $this->test->from('/contact')->post('/contact', [
            'name' => 'Vizitator Gherkin',
            'email' => 'gherkin@example.com',
            'subject' => 'Întrebare din scenariu',
            'message' => 'Acesta este un mesaj de test dintr-un scenariu Gherkin.',
            'website' => '',
            ...$extra,
        ]);
    }

    private function currentResponse(): TestResponse
    {
        if ($this->response === null) {
            throw new \LogicException('Open a page or submit a form before asserting the response.');
        }

        return $this->response;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException($message);
        }
    }
}
