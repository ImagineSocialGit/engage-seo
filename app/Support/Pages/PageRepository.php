<?php

namespace App\Support\Pages;

use App\Support\Sections\SectionManager;
use App\Support\Site\SiteLinkNormalizer;
use App\Support\Site\SitePeopleResolver;
use InvalidArgumentException;

final class PageRepository
{
    public function __construct(
        private readonly PageMetaResolver $meta,
        private readonly SectionManager $sections,
        private readonly SiteLinkNormalizer $links,
        private readonly SitePeopleResolver $people,
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
     * Resolve one configured page by its stable page key.
     *
     * @return array<string, mixed>|null
     */
    public function resolveKey(string $key): ?array
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        $definition = $this->all()[$key] ?? null;

        if (! is_array($definition)) {
            return null;
        }

        return $this->normalizePage($key, $definition);
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
     * Return every configured page using the same normalized runtime contract
     * used by public rendering.
     *
     * @return list<array<string, mixed>>
     */
    public function normalizedPages(): array
    {
        $pages = [];

        foreach ($this->all() as $key => $definition) {
            if (! is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException(
                    'Page configuration contains an invalid page key.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Page [{$key}] must be an array."
                );
            }

            $path = $definition['path'] ?? null;

            if (! is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException(
                    "Page [{$key}] is missing a valid [path]."
                );
            }

            $pages[] = $this->normalizePage($key, $definition);
        }

        return $pages;
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

            $person = $definition['person'] ?? null;

            if ($person !== null
                && (! is_string($person) || trim($person) === '')
            ) {
                $errors[] = "Page [{$key}] person must be null or a non-blank site person key.";
            } elseif (is_string($person) && $this->people->find($person) === null) {
                $errors[] = "Page [{$key}] references unknown site person [{$person}].";
            }

            try {
                $this->normalizeBreadcrumbs(
                    $definition['breadcrumbs'] ?? [],
                    $normalizedPath,
                );
            } catch (InvalidArgumentException $exception) {
                $errors[] = "Page [{$key}] {$exception->getMessage()}";
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
                $componentIsRegistered = false;

                if (! is_string($component) || trim($component) === '') {
                    $errors[] = "Page [{$key}] section [{$index}] is missing [component].";
                } else {
                    $component = trim($component);
                    $componentIsRegistered = $this->sections->registered($component);

                    if (! $componentIsRegistered) {
                        $errors[] = "Page [{$key}] section [{$index}] references unknown component [{$component}].";
                    }
                }

                $sectionStringTypesValid = true;

                foreach (['id', 'theme', 'layout'] as $stringKey) {
                    if (! array_key_exists($stringKey, $section)) {
                        continue;
                    }

                    $value = $section[$stringKey];

                    if ($value !== null && ! is_string($value)) {
                        $errors[] = "Page [{$key}] section [{$index}] [{$stringKey}] must be null or a string.";
                        $sectionStringTypesValid = false;
                    }
                }

                if ($componentIsRegistered && $sectionStringTypesValid) {
                    $theme = $this->nullableString(
                        $section['theme'] ?? null,
                        'Page section [theme]',
                    );
                    $layout = $this->nullableString(
                        $section['layout'] ?? null,
                        'Page section [layout]',
                    );

                    if (! $this->sections->supportsTheme($component, $theme)) {
                        $errors[] = "Page [{$key}] section [{$index}] uses unsupported theme [{$theme}] for component [{$component}].";
                    }

                    if (! $this->sections->supportsLayout($component, $layout)) {
                        $errors[] = "Page [{$key}] section [{$index}] uses unsupported layout [{$layout}] for component [{$component}].";
                    }
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

        $person = $definition['person'] ?? null;

        if ($person !== null) {
            if (! is_string($person) || trim($person) === '') {
                throw new InvalidArgumentException(
                    "Page [{$key}] person must be null or a non-blank site person key."
                );
            }

            $person = trim($person);

            if ($this->people->find($person) === null) {
                throw new InvalidArgumentException(
                    "Page [{$key}] references unknown site person [{$person}]."
                );
            }
        }

        return [
            'key' => $key,
            'path' => $path,
            'person' => $person,
            'breadcrumbs' => $this->normalizeBreadcrumbs(
                $definition['breadcrumbs'] ?? [],
                $path,
            ),
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

        $id = $this->nullableString(
            $section['id'] ?? null,
            'Page section [id]',
        );
        $theme = $this->nullableString(
            $section['theme'] ?? null,
            'Page section [theme]',
        );
        $layout = $this->nullableString(
            $section['layout'] ?? null,
            'Page section [layout]',
        );

        if (! $this->sections->supportsTheme($component, $theme)) {
            throw new InvalidArgumentException(
                "Unsupported Engage SEO section theme [{$theme}] for component [{$component}]."
            );
        }

        if (! $this->sections->supportsLayout($component, $layout)) {
            throw new InvalidArgumentException(
                "Unsupported Engage SEO section layout [{$layout}] for component [{$component}]."
            );
        }

        return [
            'id' => $id,
            'component' => $component,
            'theme' => $theme,
            'layout' => $layout,
            'overrides' => $overrides,
            'props' => $props,
        ];
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function normalizeBreadcrumbs(
        mixed $value,
        string $currentPath,
    ): array {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(
                'breadcrumbs must be a list.'
            );
        }

        $resolved = [];

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException(
                    "breadcrumb [{$index}] must be an array."
                );
            }

            $unknown = array_values(array_diff(
                array_keys($item),
                ['label', 'url'],
            ));

            if ($unknown !== []) {
                sort($unknown);

                throw new InvalidArgumentException(
                    "breadcrumb [{$index}] contains unsupported key(s): "
                    .implode(', ', $unknown).'.'
                );
            }

            $label = $this->nullableString(
                $item['label'] ?? null,
                "Page breadcrumb [{$index}.label]",
            );
            $url = $this->nullableString(
                $item['url'] ?? null,
                "Page breadcrumb [{$index}.url]",
            );

            if ($label === null || $url === null) {
                throw new InvalidArgumentException(
                    "breadcrumb [{$index}] requires non-blank [label] and [url]."
                );
            }

            $this->links->assertHttpOrPathUrl(
                $url,
                "pages.breadcrumbs.{$index}.url",
            );

            $resolved[] = [
                'label' => $label,
                'url' => $url,
            ];
        }

        if ($resolved !== []) {
            $last = $resolved[array_key_last($resolved)];

            if ($this->links->normalizePath($last['url'])
                !== $this->links->normalizePath($currentPath)
            ) {
                throw new InvalidArgumentException(
                    'breadcrumbs must end with the current page URL.'
                );
            }
        }

        return $resolved;
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

    private function nullableString(
        mixed $value,
        string $context,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "{$context} must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}