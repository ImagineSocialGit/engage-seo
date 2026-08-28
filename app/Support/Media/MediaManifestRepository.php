<?php

namespace App\Support\Media;

use Illuminate\Foundation\Application;
use InvalidArgumentException;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class MediaManifestRepository
{
    private const MANIFEST_VERSION = 1;

    /** @var list<string> */
    private const SOURCE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'avif',
    ];

    /** @var array<string, mixed>|null */
    private ?array $loadedManifest = null;

    public function __construct(
        private readonly Application $app,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function asset(string $key): array
    {
        $key = $this->normalizeAssetKey($key);
        $manifest = $this->manifest();
        $assets = $manifest['assets'] ?? null;

        if (! is_array($assets) || ! array_key_exists($key, $assets)) {
            throw new RuntimeException(
                "Generated media manifest does not contain asset [{$key}]."
            );
        }

        $asset = $assets[$key];

        if (! is_array($asset)) {
            throw new RuntimeException(
                "Generated media asset [{$key}] has an invalid manifest contract."
            );
        }

        return $asset;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array
    {
        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            return [];
        }

        $clientKey = trim($clientKey);
        $basePath ??= $this->app->basePath();
        $rawDirectory = rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'client'
            .DIRECTORY_SEPARATOR.$clientKey
            .DIRECTORY_SEPARATOR.'resources'
            .DIRECTORY_SEPARATOR.'images'
            .DIRECTORY_SEPARATOR.'raw';

        [$sources, $errors] = $this->rawSources($rawDirectory);
        $manifestPath = $this->manifestPath($basePath);

        if (! is_file($manifestPath)) {
            if ($sources !== []) {
                $errors[] = 'Client raw images exist but generated media manifest is missing; run the client media build.';
            }

            return array_values(array_unique($errors));
        }

        try {
            $manifest = $this->decodeManifest($manifestPath);
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();

            return array_values(array_unique($errors));
        }

        $errors = [
            ...$errors,
            ...$this->validateManifest($manifest, $manifestPath, $clientKey, $sources),
        ];

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        if ($this->loadedManifest !== null) {
            return $this->loadedManifest;
        }

        $clientKey = config('client.key');

        if (! is_string($clientKey) || trim($clientKey) === '') {
            throw new RuntimeException(
                'A selected client is required before resolving generated media.'
            );
        }

        $manifestPath = $this->manifestPath($this->app->basePath());

        if (! is_file($manifestPath)) {
            throw new RuntimeException(
                'Generated media manifest is missing; run the client media build.'
            );
        }

        $manifest = $this->decodeManifest($manifestPath);

        if (($manifest['version'] ?? null) !== self::MANIFEST_VERSION) {
            throw new RuntimeException(
                'Generated media manifest version is unsupported.'
            );
        }

        if (($manifest['client'] ?? null) !== trim($clientKey)) {
            throw new RuntimeException(
                'Generated media manifest does not belong to the selected client.'
            );
        }

        if (! is_array($manifest['assets'] ?? null)) {
            throw new RuntimeException(
                'Generated media manifest [assets] must be an object.'
            );
        }

        return $this->loadedManifest = $manifest;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $path): array
    {
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException(
                "Unable to read generated media manifest [{$path}]."
            );
        }

        try {
            $manifest = json_decode(
                $contents,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "Generated media manifest contains invalid JSON: {$exception->getMessage()}"
            );
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException(
                'Generated media manifest must be a JSON object.'
            );
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, array{path: string, sha256: string}> $sources
     * @return list<string>
     */
    private function validateManifest(
        array $manifest,
        string $manifestPath,
        string $clientKey,
        array $sources,
    ): array {
        $errors = [];

        $unknownKeys = array_values(array_diff(
            array_keys($manifest),
            ['version', 'client', 'assets'],
        ));

        if ($unknownKeys !== []) {
            $errors[] = 'Generated media manifest contains unsupported top-level keys: '.implode(', ', $unknownKeys).'.';
        }

        if (($manifest['version'] ?? null) !== self::MANIFEST_VERSION) {
            $errors[] = 'Generated media manifest version is unsupported.';
        }

        if (($manifest['client'] ?? null) !== $clientKey) {
            $errors[] = 'Generated media manifest does not belong to the selected client.';
        }

        $assets = $manifest['assets'] ?? null;

        if (! is_array($assets)) {
            $errors[] = 'Generated media manifest [assets] must be an object.';

            return $errors;
        }

        $manifestKeys = [];

        foreach ($assets as $key => $asset) {
            if (! is_string($key)) {
                $errors[] = 'Generated media manifest contains a non-string asset key.';

                continue;
            }

            try {
                $normalizedKey = $this->normalizeAssetKey($key);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();

                continue;
            }

            $manifestKeys[] = $normalizedKey;

            if (! is_array($asset)) {
                $errors[] = "Generated media asset [{$normalizedKey}] must be an object.";

                continue;
            }

            $errors = [
                ...$errors,
                ...$this->validateAsset(
                    $normalizedKey,
                    $asset,
                    dirname($manifestPath),
                    $sources[$normalizedKey] ?? null,
                ),
            ];
        }

        foreach (array_diff(array_keys($sources), $manifestKeys) as $missingKey) {
            $errors[] = "Raw media asset [{$missingKey}] is missing from the generated manifest.";
        }

        foreach (array_diff($manifestKeys, array_keys($sources)) as $extraKey) {
            $errors[] = "Generated media asset [{$extraKey}] has no matching raw source image.";
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $asset
     * @param array{path: string, sha256: string}|null $rawSource
     * @return list<string>
     */
    private function validateAsset(
        string $key,
        array $asset,
        string $mediaDirectory,
        ?array $rawSource,
    ): array {
        $errors = [];

        foreach (['width', 'height'] as $dimension) {
            if (! $this->positiveInteger($asset[$dimension] ?? null)) {
                $errors[] = "Generated media asset [{$key}] [{$dimension}] must be a positive integer.";
            }
        }

        $placeholder = $asset['placeholder'] ?? null;

        if (! is_string($placeholder)
            || ! str_starts_with($placeholder, 'data:image/webp;base64,')
            || base64_decode(substr($placeholder, strlen('data:image/webp;base64,')), true) === false
        ) {
            $errors[] = "Generated media asset [{$key}] placeholder must be a valid WebP data URI.";
        }

        $source = $asset['source'] ?? null;

        if (! is_array($source)) {
            $errors[] = "Generated media asset [{$key}] [source] must be an object.";
        } else {
            $sourcePath = $source['path'] ?? null;
            $sourceHash = $source['sha256'] ?? null;

            if (! is_string($sourcePath) || trim($sourcePath) === '') {
                $errors[] = "Generated media asset [{$key}] source path is missing.";
            } elseif ($rawSource !== null && $sourcePath !== $rawSource['path']) {
                $errors[] = "Generated media asset [{$key}] source path does not match the selected client's raw image.";
            }

            if (! is_string($sourceHash) || ! preg_match('/^[a-f0-9]{64}$/', $sourceHash)) {
                $errors[] = "Generated media asset [{$key}] source SHA-256 is invalid.";
            } elseif ($rawSource !== null && ! hash_equals($rawSource['sha256'], $sourceHash)) {
                $errors[] = "Generated media asset [{$key}] is stale because its raw source has changed.";
            }
        }

        $sources = $asset['sources'] ?? null;

        if (! is_array($sources)) {
            $errors[] = "Generated media asset [{$key}] [sources] must be an object.";
        } else {
            foreach (['avif', 'webp'] as $format) {
                $entries = $sources[$format] ?? null;

                if (! is_array($entries) || ! array_is_list($entries) || $entries === []) {
                    $errors[] = "Generated media asset [{$key}] [sources.{$format}] must be a non-empty list.";

                    continue;
                }

                $previousWidth = 0;

                foreach ($entries as $index => $entry) {
                    $entryErrors = $this->validateGeneratedEntry(
                        $key,
                        "sources.{$format}.{$index}",
                        $entry,
                        $mediaDirectory,
                        $format,
                    );

                    $errors = [...$errors, ...$entryErrors];

                    if (is_array($entry) && $this->positiveInteger($entry['width'] ?? null)) {
                        $width = $entry['width'];

                        if ($width <= $previousWidth) {
                            $errors[] = "Generated media asset [{$key}] [sources.{$format}] widths must be strictly increasing.";
                        }

                        $previousWidth = $width;
                    }
                }
            }
        }

        $fallback = $asset['fallback'] ?? null;
        $errors = [
            ...$errors,
            ...$this->validateGeneratedEntry(
                $key,
                'fallback',
                $fallback,
                $mediaDirectory,
                'webp',
            ),
        ];

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateGeneratedEntry(
        string $key,
        string $label,
        mixed $entry,
        string $mediaDirectory,
        string $expectedExtension,
    ): array {
        if (! is_array($entry)) {
            return [
                "Generated media asset [{$key}] [{$label}] must be an object.",
            ];
        }

        $errors = [];
        $path = $entry['path'] ?? null;

        if (! is_string($path)) {
            $errors[] = "Generated media asset [{$key}] [{$label}.path] is missing.";
        } else {
            try {
                $path = $this->normalizeGeneratedPath($path);

                if (! str_starts_with($path, 'assets/')
                    || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== $expectedExtension
                ) {
                    $errors[] = "Generated media asset [{$key}] [{$label}.path] has an invalid generated-media path.";
                } else {
                    $absolutePath = rtrim($mediaDirectory, DIRECTORY_SEPARATOR)
                        .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

                    if (! is_file($absolutePath)) {
                        $errors[] = "Generated media asset [{$key}] references missing file [{$path}].";
                    }
                }
            } catch (InvalidArgumentException $exception) {
                $errors[] = "Generated media asset [{$key}] [{$label}.path] {$exception->getMessage()}";
            }
        }

        foreach (['width', 'height'] as $dimension) {
            if (! $this->positiveInteger($entry[$dimension] ?? null)) {
                $errors[] = "Generated media asset [{$key}] [{$label}.{$dimension}] must be a positive integer.";
            }
        }

        return $errors;
    }

    /**
     * @return array{0: array<string, array{path: string, sha256: string}>, 1: list<string>}
     */
    private function rawSources(string $rawDirectory): array
    {
        if (! is_dir($rawDirectory)) {
            return [[], []];
        }

        $sources = [];
        $errors = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $rawDirectory,
                RecursiveDirectoryIterator::SKIP_DOTS,
            )
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relativePath = substr(
                $file->getPathname(),
                strlen(rtrim($rawDirectory, DIRECTORY_SEPARATOR)) + 1,
            );
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
            $segments = explode('/', $relativePath);

            if (array_filter($segments, fn (string $segment): bool => str_starts_with($segment, '.')) !== []) {
                continue;
            }

            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            if (! in_array($extension, self::SOURCE_EXTENSIONS, true)) {
                $errors[] = "Client raw image directory contains unsupported file [{$relativePath}].";

                continue;
            }

            $key = substr($relativePath, 0, -(strlen($extension) + 1));

            try {
                $key = $this->normalizeAssetKey($key);
            } catch (InvalidArgumentException $exception) {
                $errors[] = "Raw image [{$relativePath}] {$exception->getMessage()}";

                continue;
            }

            if (array_key_exists($key, $sources)) {
                $errors[] = "Raw images collide on media asset key [{$key}].";

                continue;
            }

            $hash = hash_file('sha256', $file->getPathname());

            if (! is_string($hash)) {
                $errors[] = "Unable to hash raw image [{$relativePath}].";

                continue;
            }

            $sources[$key] = [
                'path' => $relativePath,
                'sha256' => $hash,
            ];
        }

        ksort($sources);

        return [$sources, $errors];
    }

    private function manifestPath(string $basePath): string
    {
        $configured = config('media.manifest_path', 'public/media/manifest.json');

        if (! is_string($configured) || trim($configured) === '') {
            throw new RuntimeException(
                'Media [manifest_path] must be a non-blank path.'
            );
        }

        $configured = trim($configured);

        if ($this->absolutePath($configured)) {
            return $configured;
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $configured);
    }

    private function absolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function normalizeAssetKey(string $key): string
    {
        $key = trim($key);

        if ($key === '' || str_contains($key, '\\')) {
            throw new InvalidArgumentException(
                'Media asset key must be a non-blank forward-slash path.'
            );
        }

        $segments = explode('/', $key);

        foreach ($segments as $segment) {
            if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $segment)) {
                throw new InvalidArgumentException(
                    "Media asset key [{$key}] contains an invalid path segment."
                );
            }
        }

        return implode('/', $segments);
    }

    private function normalizeGeneratedPath(string $path): string
    {
        $path = trim($path);

        if ($path === ''
            || str_starts_with($path, '/')
            || str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
        ) {
            throw new InvalidArgumentException(
                'must be a safe relative generated-media path.'
            );
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $segment)
            ) {
                throw new InvalidArgumentException(
                    'contains an invalid generated-media path segment.'
                );
            }
        }

        return $path;
    }

    private function positiveInteger(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }
}