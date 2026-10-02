<?php

namespace Tests\Unit\Support;

use App\Support\Clients\ClientEnvironmentDefinition;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestingEnvironment;

class TestingEnvironmentTest extends TestCase
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
            .DIRECTORY_SEPARATOR.'engage-seo-testing-env-'.bin2hex(random_bytes(6));

        mkdir($this->temporaryRoot, 0777, true);

        $keys = [
            ...ClientEnvironmentDefinition::clientOwnedKeys(),
            'APP_ENV',
            'CLIENT_KEY',
            'ENGAGE_SEO_TEST_DATABASE',
            TestingEnvironment::DATABASE_SENTINEL,
        ];

        foreach (array_values(array_unique($keys)) as $key) {
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

    public function test_testing_file_replaces_inherited_selected_client_database_values(): void
    {
        file_put_contents(
            $this->temporaryRoot.'/.env.testing',
            implode(PHP_EOL, [
                'APP_URL=https://testing.example.test',
                'DB_DATABASE=isolated_test_database',
                'DB_USERNAME=test_user',
                'DB_PASSWORD=test_password',
                'ENGAGE_SEO_TEST_DATABASE=true',
                '',
            ])
        );

        $this->setEnvironmentValue('APP_ENV', 'local');
        $this->setEnvironmentValue('CLIENT_KEY', 'priority-paving-franklin');
        $this->setEnvironmentValue('APP_URL', 'https://selected-client.example.test');
        $this->setEnvironmentValue('DB_DATABASE', 'selected_client_database');
        $this->setEnvironmentValue('DB_USERNAME', 'selected_client_user');
        $this->setEnvironmentValue('DB_PASSWORD', 'selected_client_password');
        $this->setEnvironmentValue('ENGAGE_SEO_TEST_DATABASE', 'false');

        TestingEnvironment::bootstrap($this->temporaryRoot);

        $this->assertSame('testing', getenv('APP_ENV'));
        $this->assertSame('', getenv('CLIENT_KEY'));
        $this->assertSame(
            'https://testing.example.test',
            getenv('APP_URL')
        );
        $this->assertSame(
            'isolated_test_database',
            getenv('DB_DATABASE')
        );
        $this->assertSame('test_user', getenv('DB_USERNAME'));
        $this->assertSame('test_password', getenv('DB_PASSWORD'));
        $this->assertSame('true', getenv('ENGAGE_SEO_TEST_DATABASE'));
        $this->assertSame(
            'isolated_test_database',
            getenv(TestingEnvironment::DATABASE_SENTINEL)
        );
    }

    public function test_missing_testing_file_clears_inherited_client_database_and_fails_closed(): void
    {
        $this->setEnvironmentValue('APP_ENV', 'local');
        $this->setEnvironmentValue('CLIENT_KEY', 'priority-paving-franklin');
        $this->setEnvironmentValue('DB_DATABASE', 'selected_client_database');
        $this->setEnvironmentValue('DB_USERNAME', 'selected_client_user');
        $this->setEnvironmentValue('DB_PASSWORD', 'selected_client_password');
        $this->setEnvironmentValue('ENGAGE_SEO_TEST_DATABASE', 'true');
        $this->setEnvironmentValue(
            TestingEnvironment::DATABASE_SENTINEL,
            'selected_client_database',
        );

        TestingEnvironment::bootstrap($this->temporaryRoot);

        $this->assertSame('testing', getenv('APP_ENV'));
        $this->assertSame('', getenv('CLIENT_KEY'));
        $this->assertFalse(getenv('DB_DATABASE'));
        $this->assertFalse(getenv('DB_USERNAME'));
        $this->assertFalse(getenv('DB_PASSWORD'));
        $this->assertFalse(getenv('ENGAGE_SEO_TEST_DATABASE'));
        $this->assertFalse(
            getenv(TestingEnvironment::DATABASE_SENTINEL)
        );
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

        if ($items === false) {
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