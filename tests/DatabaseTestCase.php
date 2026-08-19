<?php

namespace Tests;

use Illuminate\Support\Env;
use RuntimeException;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestingDatabase();
    }

    private function assertSafeTestingDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException(
                'Database-backed tests may run only with APP_ENV=testing.'
            );
        }

        if (! is_file(base_path('.env.testing'))) {
            throw new RuntimeException(
                'Database-backed tests require a root .env.testing file. '
                .'Copy .env.testing.example to .env.testing and configure a dedicated MySQL test database.'
            );
        }

        if (config('client.key') !== null) {
            throw new RuntimeException(
                'Database-backed tests must run with CLIENT_KEY blank so no real client environment can be selected.'
            );
        }

        if (config('database.default') !== 'mysql') {
            throw new RuntimeException(
                'Engage SEO database-backed tests require the MySQL connection.'
            );
        }

        $database = trim((string) config('database.connections.mysql.database'));

        if ($database === '') {
            throw new RuntimeException(
                'Database-backed tests require DB_DATABASE in .env.testing.'
            );
        }

        $guard = filter_var(
            Env::get('ENGAGE_SEO_TEST_DATABASE', false),
            FILTER_VALIDATE_BOOL,
        );

        if (! $guard) {
            throw new RuntimeException(
                'Database-backed tests require ENGAGE_SEO_TEST_DATABASE=true in .env.testing.'
            );
        }
    }
}