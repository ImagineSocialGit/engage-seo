<?php

namespace App\Support\Clients;

use App\Support\Config\ConfigMerger;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

class ClientConfigLoader
{
    public function __construct(
        protected Application $app,
        protected Repository $config,
    ) {
    }

    public function load(?string $clientKey = null): ?string
    {
        if ($this->app->configurationIsCached()) {
            return $this->configuredClientKey();
        }

        $clientKey = $this->normalizeClientKey(
            $clientKey ?? $this->environmentClientKey()
        );

        if ($clientKey === null) {
            return null;
        }

        $clientDirectory = $this->app->basePath("clients/{$clientKey}");
        $configDirectory = $clientDirectory.'/config';

        if (! is_dir($clientDirectory)) {
            throw new RuntimeException(
                "Selected Engage SEO client does not exist: {$clientKey}"
            );
        }

        if (! is_dir($configDirectory)) {
            throw new RuntimeException(
                "Selected Engage SEO client has no config directory: {$clientKey}"
            );
        }

        foreach ($this->configFiles($configDirectory) as $file) {
            $configKey = $this->configKey($configDirectory, $file);
            $clientConfig = require $file;

            if (! is_array($clientConfig)) {
                throw new RuntimeException(
                    "Client config must return an array: {$file}"
                );
            }

            $baseConfig = $this->config->get($configKey, []);

            if (! is_array($baseConfig)) {
                $baseConfig = [];
            }

            $this->config->set(
                $configKey,
                ConfigMerger::merge($baseConfig, $clientConfig)
            );
        }

        $configuredKey = $this->config->get('client.key');

        if ($configuredKey !== $clientKey) {
            throw new RuntimeException(
                "Selected client key '{$clientKey}' does not match config('client.key')."
            );
        }

        return $clientKey;
    }

    protected function configuredClientKey(): ?string
    {
        $value = $this->config->get('client.key');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    protected function environmentClientKey(): ?string
    {
        $value = Env::get('CLIENT_KEY');

        return is_string($value) ? $value : null;
    }

    protected function normalizeClientKey(?string $clientKey): ?string
    {
        if ($clientKey === null || trim($clientKey) === '') {
            return null;
        }

        $clientKey = trim($clientKey);

        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $clientKey)) {
            throw new InvalidArgumentException(
                "Invalid Engage SEO client key: {$clientKey}"
            );
        }

        return $clientKey;
    }

    /**
     * @return list<string>
     */
    protected function configFiles(string $configDirectory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $configDirectory,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $files = [];

        foreach ($iterator as $file) {
            if (
                $file instanceof SplFileInfo
                && $file->isFile()
                && $file->getExtension() === 'php'
            ) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    protected function configKey(
        string $configDirectory,
        string $file
    ): string {
        $relativePath = substr(
            $file,
            strlen(rtrim($configDirectory, DIRECTORY_SEPARATOR)) + 1
        );

        $withoutExtension = substr($relativePath, 0, -4);

        return str_replace(
            DIRECTORY_SEPARATOR,
            '.',
            $withoutExtension
        );
    }
}