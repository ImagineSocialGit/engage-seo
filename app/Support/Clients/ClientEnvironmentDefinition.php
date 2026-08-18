<?php

namespace App\Support\Clients;

final class ClientEnvironmentDefinition
{
    /**
     * Environment keys that belong to the selected client deployment.
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
     * @var list<string>
     */
    private const REQUIRED_CLIENT_KEYS = [
        'APP_URL',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    /**
     * @return list<string>
     */
    public static function clientOwnedKeys(): array
    {
        return self::CLIENT_OWNED_KEYS;
    }

    /**
     * @return list<string>
     */
    public static function requiredClientKeys(): array
    {
        return self::REQUIRED_CLIENT_KEYS;
    }
}