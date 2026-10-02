<?php

namespace Tests\Support;

use App\Support\Clients\ClientEnvironmentDefinition;
use Dotenv\Dotenv;

final class TestingEnvironment
{
    public const DATABASE_SENTINEL = 'ENGAGE_SEO_TEST_BOOTSTRAP_DATABASE';

    public static function bootstrap(string $basePath): void
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        self::clearEnvironmentValue(self::DATABASE_SENTINEL);
        self::clearEnvironmentValue('ENGAGE_SEO_TEST_DATABASE');

        foreach (ClientEnvironmentDefinition::clientOwnedKeys() as $key) {
            self::clearEnvironmentValue($key);
        }

        self::setEnvironmentValue('APP_ENV', 'testing');
        self::setEnvironmentValue('CLIENT_KEY', '');

        $environmentPath = $basePath.DIRECTORY_SEPARATOR.'.env.testing';

        if (! is_file($environmentPath)) {
            return;
        }

        $values = Dotenv::createArrayBacked(
            $basePath,
            '.env.testing',
        )->safeLoad();

        foreach ($values as $key => $value) {
            self::setEnvironmentValue($key, $value);
        }

        // Testing identity is platform-owned and cannot be redefined by
        // .env.testing. Database credentials remain .env.testing-owned.
        self::setEnvironmentValue('APP_ENV', 'testing');
        self::setEnvironmentValue('CLIENT_KEY', '');

        $database = $values['DB_DATABASE'] ?? null;

        if (is_string($database) && trim($database) !== '') {
            self::setEnvironmentValue(
                self::DATABASE_SENTINEL,
                trim($database),
            );
        }
    }

    private static function clearEnvironmentValue(string $key): void
    {
        putenv($key);

        unset($_ENV[$key], $_SERVER[$key]);
    }

    private static function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}