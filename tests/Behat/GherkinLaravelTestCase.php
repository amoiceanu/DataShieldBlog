<?php

namespace Tests\Behat;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

class GherkinLaravelTestCase extends TestCase
{
    public function startScenario(): void
    {
        $this->useIsolatedTestEnvironment();
        $this->setUp();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed();
    }

    public function stopScenario(): void
    {
        if (isset($this->app)) {
            $this->app['db']->disconnect();
            $this->app->flush();
            $this->app = null;
        }

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    public function hasContactMessage(string $email, string $subject): bool
    {
        return DB::table('contact_messages')->where(compact('email', 'subject'))->exists();
    }

    public function contactMessageCount(): int
    {
        return DB::table('contact_messages')->count();
    }

    public function sessionHas(string $key, mixed $value): bool
    {
        return $this->app['session']->get($key) === $value;
    }

    public function sessionHasValidationError(string $key): bool
    {
        $errors = $this->app['session']->get('errors');

        return $errors !== null && $errors->has($key);
    }

    protected function setUp(): void
    {
        $this->useIsolatedTestEnvironment();
        parent::setUp();
    }

    private function useIsolatedTestEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
