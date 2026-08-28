<?php

namespace App\Support\Clients;

use RuntimeException;

final class ClientEnvironmentDefinition
{
    /**
     * Environment keys that belong to every selected client deployment.
     *
     * Root .env owns process/machine infrastructure. These values are cleared
     * before the selected client's .env is applied so stale root values cannot
     * override the selected client.
     *
     * @var list<string>
     */
    private const CLIENT_OWNED_KEYS = [
        'APP_URL',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'CACHE_PREFIX',
        'REDIS_PREFIX',
        'SESSION_DOMAIN',
    ];

    /**
     * Keys that must be present in a selected client's .env for a complete
     * database-backed local/deployed site configuration.
     *
     * Package-declared integration secrets are intentionally not universally
     * required; the owning package's setup validator decides whether one is
     * required for the selected client's configuration.
     *
     * @var list<string>
     */
    private const REQUIRED_CLIENT_KEYS = [
        'APP_URL',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * Return selected-client-owned keys plus optional keys declared by the
     * selected client's installed-package configuration.
     *
     * Package environment keys must use the ENGAGE_SEO_ namespace so a client
     * package cannot redefine Laravel/process infrastructure such as APP_ENV,
     * APP_KEY, CLIENT_KEY, DB_HOST, or queue/cache drivers.
     *
     * @return list<string>
     */
    public static function clientOwnedKeys(?string $clientDirectory = null): array
    {
        $keys = self::CLIENT_OWNED_KEYS;

        if ($clientDirectory !== null) {
            $keys = [
                ...$keys,
                ...self::packageEnvironmentKeys($clientDirectory),
            ];
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    public static function packageEnvironmentKeys(string $clientDirectory): array
    {
        $configPath = rtrim($clientDirectory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'config'
            .DIRECTORY_SEPARATOR.'client_packages.php';

        if (! is_file($configPath)) {
            return [];
        }

        $config = require $configPath;

        if (! is_array($config)) {
            throw new RuntimeException(
                'Client config/client_packages.php must return an array.'
            );
        }

        $keys = $config['environment_keys'] ?? [];

        if (! is_array($keys) || ! array_is_list($keys)) {
            throw new RuntimeException(
                'Client package [environment_keys] must be a list.'
            );
        }

        $normalized = [];

        foreach ($keys as $index => $key) {
            if (! is_string($key)
                || preg_match('/^ENGAGE_SEO_[A-Z0-9_]+$/', $key) !== 1
            ) {
                throw new RuntimeException(
                    "Client package environment key [{$index}] must use the ENGAGE_SEO_ namespace and contain only uppercase letters, numbers, and underscores."
                );
            }

            $normalized[] = $key;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return list<string>
     */
    public static function requiredClientKeys(): array
    {
        return self::REQUIRED_CLIENT_KEYS;
    }
}