<?php

namespace App\Support\Clients;

use Dotenv\Dotenv;
use Illuminate\Support\Env;
use InvalidArgumentException;
use RuntimeException;

final class ClientEnvironmentLoader
{
    public function load(string $basePath): void
    {
        $clientKey = $this->clientKey();

        if ($clientKey === null) {
            return;
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey)) {
            throw new InvalidArgumentException(
                "Invalid Engage SEO CLIENT_KEY: {$clientKey}"
            );
        }

        $clientDirectory = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'client'
            .DIRECTORY_SEPARATOR.$clientKey;

        if (! is_dir($clientDirectory)) {
            throw new RuntimeException(
                "Selected Engage SEO client directory does not exist: {$clientKey}"
            );
        }

        $ownedKeys = ClientEnvironmentDefinition::clientOwnedKeys(
            $clientDirectory,
        );
        $environmentPath = $clientDirectory.DIRECTORY_SEPARATOR.'.env';

        if (! is_file($environmentPath)) {
            foreach ($ownedKeys as $key) {
                $this->clearEnvironmentValue($key);
            }

            return;
        }

        $values = Dotenv::createArrayBacked(
            $clientDirectory,
            '.env',
        )->safeLoad();

        $unsupportedKeys = array_values(array_diff(
            array_keys($values),
            $ownedKeys,
        ));

        sort($unsupportedKeys);

        if ($unsupportedKeys !== []) {
            throw new RuntimeException(sprintf(
                'Client environment [%s] contains root-owned or unsupported key(s): %s.',
                $environmentPath,
                implode(', ', $unsupportedKeys),
            ));
        }

        foreach ($ownedKeys as $key) {
            $this->clearEnvironmentValue($key);
        }

        foreach ($values as $key => $value) {
            $this->setEnvironmentValue($key, $value);
        }
    }

    private function clientKey(): ?string
    {
        $clientKey = Env::get('CLIENT_KEY');

        if (! is_string($clientKey)) {
            return null;
        }

        $clientKey = trim($clientKey);

        return $clientKey !== ''
            ? $clientKey
            : null;
    }

    private function clearEnvironmentValue(string $key): void
    {
        putenv($key);

        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}