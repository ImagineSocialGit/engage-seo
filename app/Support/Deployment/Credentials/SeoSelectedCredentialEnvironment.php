<?php

namespace App\Support\Deployment\Credentials;

use App\Support\Clients\ClientEnvironmentDefinition;
use RuntimeException;

/** Private, read-only selected-client dotenv snapshot, never a public report. */
final class SeoSelectedCredentialEnvironment
{
    /** @return array<string, string> */
    public function read(string $basePath, string $client): array
    {
        if (preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $client) !== 1) {
            throw new RuntimeException('Invalid selected SEO client.');
        }
        $root = rtrim($basePath, DIRECTORY_SEPARATOR);
        $directory = $root.'/client/'.$client;
        $path = $directory.'/.env';

        if (! is_dir($root) || is_link($root)
            || ! is_dir($root.'/client') || is_link($root.'/client')
            || ! is_dir($directory) || is_link($directory)
        ) {
            throw new RuntimeException('Unsafe selected SEO client directory.');
        }

        $configurationDirectory = $directory.'/config';
        $packagesFile = $configurationDirectory.'/client_packages.php';
        if (! is_dir($configurationDirectory) || is_link($configurationDirectory)
            || is_link($packagesFile)
        ) {
            throw new RuntimeException('Unsafe selected SEO package configuration.');
        }

        $allowed = ClientEnvironmentDefinition::clientOwnedKeys($directory);
        if (! file_exists($path) && ! is_link($path)) {
            return [];
        }
        if (! is_file($path) || is_link($path)) {
            throw new RuntimeException('Unsafe selected SEO environment file.');
        }
        $stat = lstat($path);
        if (! is_array($stat) || ($stat['mode'] & 0777) !== 0600
            || ($stat['nlink'] ?? 0) !== 1 || ($stat['size'] ?? 0) > 1048576
        ) {
            throw new RuntimeException('Selected SEO environment file is not private.');
        }
        $data = file_get_contents($path);
        if (! is_string($data) || strlen($data) > 1048576 || str_contains($data, "\r")
            || str_contains($data, "\0")
        ) {
            throw new RuntimeException('Invalid selected SEO environment contents.');
        }

        $values = [];
        foreach (explode("\n", $data) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (preg_match('/\A([A-Za-z_][A-Za-z0-9_]*)=(.*)\z/D', $line, $matches) !== 1) {
                throw new RuntimeException('Unsupported selected SEO environment syntax.');
            }
            [$ignored, $key, $raw] = $matches;
            if (array_key_exists($key, $values) || ! in_array($key, $allowed, true)) {
                throw new RuntimeException('Invalid or duplicate selected SEO environment key.');
            }
            if (str_starts_with($raw, '"') || str_starts_with($raw, "'")) {
                if (strlen($raw) < 2 || substr($raw, -1) !== $raw[0]) {
                    throw new RuntimeException('Unsupported selected SEO environment quotation.');
                }
                $raw = substr($raw, 1, -1);
                if (str_contains($raw, '\\')) {
                    throw new RuntimeException('Unsupported selected SEO environment escaping.');
                }
            } elseif (preg_match('/[\s\x22\x27#]/', $raw) === 1) {
                throw new RuntimeException('Unsupported selected SEO environment syntax.');
            }
            $values[$key] = $raw;
        }
        return $values;
    }
}