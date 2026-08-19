<?php

namespace App\Support\Media;

use InvalidArgumentException;

final class MediaUrlResolver
{
    public function resolve(string $generatedPath): string
    {
        $generatedPath = $this->normalizeGeneratedPath($generatedPath);

        return rtrim($this->baseUrl(), '/').'/'.$generatedPath;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        try {
            $this->baseUrl();
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }

        return [];
    }

    private function baseUrl(): string
    {
        $configured = config('media.base_url');

        if ($configured !== null) {
            if (! is_string($configured) || trim($configured) === '') {
                throw new InvalidArgumentException(
                    'Media [base_url] must be null or a non-blank string.'
                );
            }

            return $this->normalizeBaseUrl(
                trim($configured),
                'Media [base_url]',
            );
        }

        $prefix = config('media.public_prefix', '/media');

        if (! is_string($prefix) || trim($prefix) === '') {
            throw new InvalidArgumentException(
                'Media [public_prefix] must be a non-blank absolute site path.'
            );
        }

        $prefix = trim($prefix);

        if (! str_starts_with($prefix, '/')
            || str_contains($prefix, '?')
            || str_contains($prefix, '#')
        ) {
            throw new InvalidArgumentException(
                'Media [public_prefix] must be an absolute site path without a query string or fragment.'
            );
        }

        return '/'.trim($prefix, '/');
    }

    private function normalizeBaseUrl(string $value, string $label): string
    {
        if (str_starts_with($value, '/')) {
            if (str_contains($value, '?') || str_contains($value, '#')) {
                throw new InvalidArgumentException(
                    "{$label} must not contain a query string or fragment."
                );
            }

            return '/'.trim($value, '/');
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);
        $query = parse_url($value, PHP_URL_QUERY);
        $fragment = parse_url($value, PHP_URL_FRAGMENT);

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || trim($host) === ''
            || $query !== null
            || $fragment !== null
        ) {
            throw new InvalidArgumentException(
                "{$label} must be an absolute http/https URL or absolute site path without a query string or fragment."
            );
        }

        return rtrim($value, '/');
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
                'Generated media path must be a non-blank relative path without query strings or fragments.'
            );
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new InvalidArgumentException(
                    'Generated media path contains an unsafe path segment.'
                );
            }
        }

        return implode('/', $segments);
    }
}