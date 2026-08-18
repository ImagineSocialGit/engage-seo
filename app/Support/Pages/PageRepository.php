<?php

namespace App\Support\Pages;

use App\Support\Sections\SectionManager;
use InvalidArgumentException;

final class PageRepository
{
    public function __construct(
        private readonly PageMetaResolver $meta,
        private readonly SectionManager $sections,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $pages = config('pages', []);

        return is_array($pages)
            ? $pages
            : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePath(string $path): ?array
    {
        $path = $this->normalizePath($path);

        foreach ($this->all() as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }

            $configuredPath = $definition['path'] ?? null;

            if (! is_string($configuredPath) || trim($configuredPath) === '') {
                continue;
            }

            if ($this->normalizePath($configuredPath) !== $path) {
                continue;
            }

            return $this->normalizePage($key, $definition);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];
        $paths = [];

        foreach ($this->all() as $key => $definition) {
            if (! is_string($key) || trim($key) === '') {
                $errors[] = 'Page configuration contains an invalid page key.';

                continue;
            }

            if (! is_array($definition)) {
                $errors[] = "Page [{$key}] must be an array.";

                continue;
            }

            $path = $definition['path'] ?? null;

            if (! is_string($path) || trim($path) === '') {
                $errors[] = "Page [{$key}] is missing a valid [path].";

                continue;
            }

            try {
                $normalizedPath = $this->normalizePath($path);
            } catch (InvalidArgumentException $exception) {
                $errors[] = "Page [{$key}] {$exception->getMessage()}";

                continue;
            }

            if (array_key_exists($normalizedPath, $paths)) {
                $errors[] = sprintf(
                    'Pages [%s] and [%s] resolve to the same path [%s].',
                    $paths[$normalizedPath],
                    $key,
                    $normalizedPath,
                );
            } else {
                $paths[$normalizedPath] = $key;
            }

            $meta = $definition['meta'] ?? [];

            if (! is_array($meta)) {
                $errors[] = "Page [{$key}] meta must be an array.";
            } else {
                try {
                    $this->meta->resolve($meta, $normalizedPath);
                } catch (InvalidArgumentException $exception) {
                    $errors[] = "Page [{$key}] {$exception->getMessage()}";
                }
            }

            $sections = $definition['sections'] ?? [];

            if (! is_array($sections) || ! array_is_list($sections)) {
                $errors[] = "Page [{$key}] sections must be a list.";

                continue;
            }

            foreach ($sections as $index => $section) {
                if (! is_array($section)) {
                    $errors[] = "Page [{$key}] section [{$index}] must be an array.";

                    continue;
                }

                $component = $section['component'] ?? null;

                if (! is_string($component) || trim($component) === '') {
                    $errors[] = "Page [{$key}] section [{$index}] is missing [component].";
                } elseif (! $this->sections->registered($component)) {
                    $errors[] = "Page [{$key}] section [{$index}] references unknown component [{$component}].";
                }

                foreach (['props', 'overrides'] as $arrayKey) {
                    if (
                        array_key_exists($arrayKey, $section)
                        && ! is_array($section[$arrayKey])
                    ) {
                        $errors[] = "Page [{$key}] section [{$index}] [{$arrayKey}] must be an array.";
                    }
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function normalizePage(string $key, array $definition): array
    {
        $path = $this->normalizePath((string) $definition['path']);

        $meta = $definition['meta'] ?? [];

        if (! is_array($meta)) {
            throw new InvalidArgumentException(
                "Page [{$key}] meta must be an array."
            );
        }

        $sections = $definition['sections'] ?? [];

        if (! is_array($sections) || ! array_is_list($sections)) {
            throw new InvalidArgumentException(
                "Page [{$key}] sections must be a list."
            );
        }

        $normalizedSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                throw new InvalidArgumentException(
                    "Page [{$key}] sections must contain arrays."
                );
            }

            $normalizedSections[] = $this->normalizeSection($section);
        }

        return [
            'key' => $key,
            'path' => $path,
            'meta' => $this->meta->resolve($meta, $path),
            'sections' => $normalizedSections,
        ];
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    private function normalizeSection(array $section): array
    {
        $component = $section['component'] ?? null;

        if (! is_string($component) || trim($component) === '') {
            throw new InvalidArgumentException(
                'Page section is missing [component].'
            );
        }

        $component = trim($component);

        if (! $this->sections->registered($component)) {
            throw new InvalidArgumentException(
                "Unknown Engage SEO section component: {$component}"
            );
        }

        $props = $section['props'] ?? [];
        $overrides = $section['overrides'] ?? [];

        if (! is_array($props) || ! is_array($overrides)) {
            throw new InvalidArgumentException(
                'Page section [props] and [overrides] must be arrays.'
            );
        }

        return [
            'id' => $this->nullableString($section['id'] ?? null),
            'component' => $component,
            'theme' => $this->nullableString($section['theme'] ?? null),
            'layout' => $this->nullableString($section['layout'] ?? null),
            'overrides' => $overrides,
            'props' => $props,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        if (str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidArgumentException(
                'path must not contain a query string or fragment.'
            );
        }

        if (! str_starts_with($path, '/')) {
            throw new InvalidArgumentException(
                'path must start with a forward slash.'
            );
        }

        return '/'.trim($path, '/');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}