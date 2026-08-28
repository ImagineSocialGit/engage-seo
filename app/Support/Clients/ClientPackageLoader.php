<?php

namespace App\Support\Clients;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

final class ClientPackageLoader
{
    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
    ) {
    }

    public function loadAutoloader(
        ?string $clientKey = null,
        ?string $basePath = null,
    ): void {
        $clientKey = $this->clientKey($clientKey);

        if ($clientKey === null) {
            return;
        }

        $basePath ??= $this->app->basePath();
        $clientDirectory = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'client'
            .DIRECTORY_SEPARATOR.$clientKey;
        $composerPath = $clientDirectory.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerPath)) {
            return;
        }

        $autoloadPath = $clientDirectory
            .DIRECTORY_SEPARATOR.'vendor'
            .DIRECTORY_SEPARATOR.'autoload.php';

        if (! is_file($autoloadPath)) {
            throw new RuntimeException(
                "Selected Engage SEO client [{$clientKey}] declares Composer packages but has no installed vendor/autoload.php. Run composer install inside client/{$clientKey}."
            );
        }

        require_once $autoloadPath;
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    public function providerClasses(): array
    {
        $raw = $this->config->get('client_packages.providers', []);

        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidArgumentException(
                'Engage SEO [client_packages.providers] must be a list.'
            );
        }

        $providers = [];

        foreach ($raw as $index => $provider) {
            if (! is_string($provider) || trim($provider) === '') {
                throw new InvalidArgumentException(
                    "Engage SEO client package provider [{$index}] must be a non-blank class name."
                );
            }

            $provider = trim($provider);

            if (! class_exists($provider)) {
                throw new RuntimeException(
                    "Engage SEO client package provider [{$provider}] is not installed or autoloadable."
                );
            }

            if (! is_subclass_of($provider, ServiceProvider::class)) {
                throw new InvalidArgumentException(
                    "Engage SEO client package provider [{$provider}] must extend ".ServiceProvider::class.'.'
                );
            }

            $providers[] = $provider;
        }

        return array_values(array_unique($providers));
    }

    public function registerProviders(): void
    {
        foreach ($this->providerClasses() as $provider) {
            $this->app->register($provider);
        }
    }

    private function clientKey(?string $clientKey): ?string
    {
        $clientKey ??= $this->config->get('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            $clientKey = Env::get('CLIENT_KEY');
        }

        if (! is_string($clientKey) || trim($clientKey) === '') {
            return null;
        }

        $clientKey = trim($clientKey);

        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey) !== 1) {
            throw new InvalidArgumentException(
                "Selected Engage SEO client key is invalid: {$clientKey}"
            );
        }

        return $clientKey;
    }
}