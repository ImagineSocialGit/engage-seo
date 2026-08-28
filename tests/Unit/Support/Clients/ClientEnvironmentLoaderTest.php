<?php

namespace Tests\Unit\Support\Clients;

use App\Support\Clients\ClientEnvironmentDefinition;
use App\Support\Clients\ClientEnvironmentLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ClientEnvironmentLoaderTest extends TestCase
{
    private string $temporaryRoot;

    /**
     * @var array<string, string|false|null>
     */
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'engage-seo-client-env-'.bin2hex(random_bytes(6));

        mkdir($this->temporaryRoot.DIRECTORY_SEPARATOR.'client', 0777, true);

        $keys = [
            'CLIENT_KEY',
            ...ClientEnvironmentDefinition::clientOwnedKeys(),
            'ENGAGE_SEO_FIXTURE_PROVIDER_KEY',
        ];

        foreach ($keys as $key) {
            $this->previousEnvironment[$key] = getenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnvironment as $key => $value) {
            if ($value === false || $value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_selected_client_values_replace_stale_client_owned_environment_values(): void
    {
        $clientDirectory = $this->temporaryRoot.'/client/example-client';
        mkdir($clientDirectory, 0777, true);

        file_put_contents(
            $clientDirectory.'/.env',
            implode(PHP_EOL, [
                'APP_URL=https://client.example.test',
                'DB_DATABASE=client_database',
                'DB_USERNAME=client_user',
                'DB_PASSWORD=client_password',
                'CACHE_PREFIX=client-cache-',
                'REDIS_PREFIX=client-redis-',
                '',
            ])
        );

        $this->setEnvironmentValue('CLIENT_KEY', 'example-client');
        $this->setEnvironmentValue('APP_URL', 'https://stale-root.example.test');
        $this->setEnvironmentValue('DB_DATABASE', 'stale_root_database');

        (new ClientEnvironmentLoader())->load($this->temporaryRoot);

        $this->assertSame(
            'https://client.example.test',
            getenv('APP_URL')
        );
        $this->assertSame(
            'client_database',
            getenv('DB_DATABASE')
        );
        $this->assertSame(
            'client_user',
            getenv('DB_USERNAME')
        );
    }

    public function test_package_declared_client_environment_key_is_allowed_and_applied(): void
    {
        $clientDirectory = $this->temporaryRoot.'/client/example-client';
        mkdir($clientDirectory.'/config', 0777, true);

        file_put_contents(
            $clientDirectory.'/config/client_packages.php',
            <<<'PHP'
<?php

return [
    'environment_keys' => [
        'ENGAGE_SEO_FIXTURE_PROVIDER_KEY',
    ],
];
PHP
        );

        file_put_contents(
            $clientDirectory.'/.env',
            implode(PHP_EOL, [
                'APP_URL=https://client.example.test',
                'ENGAGE_SEO_FIXTURE_PROVIDER_KEY=fixture-secret',
                '',
            ])
        );

        $this->setEnvironmentValue('CLIENT_KEY', 'example-client');
        $this->setEnvironmentValue(
            'ENGAGE_SEO_FIXTURE_PROVIDER_KEY',
            'stale-value',
        );

        (new ClientEnvironmentLoader())->load($this->temporaryRoot);

        $this->assertSame(
            'fixture-secret',
            getenv('ENGAGE_SEO_FIXTURE_PROVIDER_KEY')
        );
    }

    public function test_package_environment_keys_must_use_reserved_namespace(): void
    {
        $clientDirectory = $this->temporaryRoot.'/client/example-client';
        mkdir($clientDirectory.'/config', 0777, true);

        file_put_contents(
            $clientDirectory.'/config/client_packages.php',
            <<<'PHP'
<?php

return [
    'environment_keys' => [
        'APP_ENV',
    ],
];
PHP
        );

        $this->setEnvironmentValue('CLIENT_KEY', 'example-client');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ENGAGE_SEO_ namespace');

        (new ClientEnvironmentLoader())->load($this->temporaryRoot);
    }

    public function test_client_environment_rejects_root_owned_or_unknown_keys_before_mutating_environment(): void
    {
        $clientDirectory = $this->temporaryRoot.'/client/example-client';
        mkdir($clientDirectory, 0777, true);

        file_put_contents(
            $clientDirectory.'/.env',
            implode(PHP_EOL, [
                'APP_URL=https://client.example.test',
                'APP_ENV=production',
                '',
            ])
        );

        $this->setEnvironmentValue('CLIENT_KEY', 'example-client');
        $this->setEnvironmentValue('APP_URL', 'https://existing.example.test');

        $this->expectException(RuntimeException::class);

        try {
            (new ClientEnvironmentLoader())->load($this->temporaryRoot);
        } finally {
            $this->assertSame(
                'https://existing.example.test',
                getenv('APP_URL')
            );
        }
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}