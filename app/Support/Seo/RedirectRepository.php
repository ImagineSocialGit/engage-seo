<?php

namespace App\Support\Seo;

use InvalidArgumentException;

final class RedirectRepository
{
    /**
     * @return array{from: string, to: string, status: int}|null
     */
    public function resolve(string $path): ?array
    {
        $path = $this->normalizeSourcePath($path);

        foreach ($this->normalized() as $redirect) {
            if ($redirect['from'] === $path) {
                return $redirect;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $configured = config('site.seo.redirects', []);

        if (! is_array($configured) || ! array_is_list($configured)) {
            return [
                'Site SEO [redirects] must be a list.',
            ];
        }

        $errors = [];
        $valid = [];

        foreach ($configured as $index => $redirect) {
            try {
                $valid[] = $this->normalizeEntry($redirect, $index);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $sources = [];

        foreach ($valid as $redirect) {
            if (array_key_exists($redirect['from'], $sources)) {
                $errors[] = sprintf(
                    'Site SEO redirects contain duplicate source path [%s].',
                    $redirect['from'],
                );
            } else {
                $sources[$redirect['from']] = true;
            }
        }

        foreach ($valid as $redirect) {
            $targetPath = $this->internalTargetPath($redirect['to']);

            if ($targetPath !== null && array_key_exists($targetPath, $sources)) {
                $errors[] = sprintf(
                    'Site SEO redirect [%s] targets another configured redirect source [%s]; redirect chains and cycles are not allowed.',
                    $redirect['from'],
                    $targetPath,
                );
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return list<array{from: string, to: string, status: int}>
     */
    private function normalized(): array
    {
        $configured = config('site.seo.redirects', []);

        if (! is_array($configured) || ! array_is_list($configured)) {
            throw new InvalidArgumentException(
                'Site SEO [redirects] must be a list.'
            );
        }

        $errors = $this->validationErrors();

        if ($errors !== []) {
            throw new InvalidArgumentException($errors[0]);
        }

        return array_map(
            fn (mixed $redirect, int $index): array => $this->normalizeEntry($redirect, $index),
            $configured,
            array_keys($configured),
        );
    }

    /**
     * @return array{from: string, to: string, status: int}
     */
    private function normalizeEntry(mixed $redirect, int $index): array
    {
        if (! is_array($redirect)) {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] must be an array."
            );
        }

        $unknown = array_values(array_diff(
            array_keys($redirect),
            ['from', 'to', 'status'],
        ));

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Site SEO redirect [%d] contains unsupported key(s): %s.',
                $index,
                implode(', ', $unknown),
            ));
        }

        $from = $redirect['from'] ?? null;
        $to = $redirect['to'] ?? null;
        $status = $redirect['status'] ?? 301;

        if (! is_string($from) || trim($from) === '') {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] requires a non-blank [from] path."
            );
        }

        if (! is_string($to) || trim($to) === '') {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] requires a non-blank [to] destination."
            );
        }

        if (! is_int($status) || ! in_array($status, [301, 302, 307, 308], true)) {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] [status] must be one of 301, 302, 307, or 308."
            );
        }

        $from = $this->normalizeSourcePath($from);
        $to = trim($to);

        $this->assertDestination($to, $index);

        $targetPath = $this->internalTargetPath($to);

        if ($targetPath !== null && $targetPath === $from) {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] cannot redirect [{$from}] to itself."
            );
        }

        return [
            'from' => $from,
            'to' => $to,
            'status' => $status,
        ];
    }

    private function normalizeSourcePath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new InvalidArgumentException(
                'SEO redirect source paths must begin with a forward slash.'
            );
        }

        if (str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidArgumentException(
                'SEO redirect source paths must not contain a query string or fragment.'
            );
        }

        return $path === '/'
            ? '/'
            : '/'.trim($path, '/');
    }

    private function assertDestination(string $destination, int $index): void
    {
        if (str_starts_with($destination, '/')) {
            return;
        }

        $scheme = parse_url($destination, PHP_URL_SCHEME);
        $host = parse_url($destination, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true)
            || ! is_string($host)
            || trim($host) === ''
        ) {
            throw new InvalidArgumentException(
                "Site SEO redirect [{$index}] [to] must be an absolute site path or an absolute http/https URL."
            );
        }
    }

    private function internalTargetPath(string $destination): ?string
    {
        if (! str_starts_with($destination, '/')) {
            return null;
        }

        $path = parse_url($destination, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        return $this->normalizeSourcePath($path);
    }
}