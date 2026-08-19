<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class SiteLinkNormalizer
{
    /**
     * @return list<array<string, mixed>>
     */
    public function navigationItems(
        mixed $items,
        string $currentPath,
        string $context,
    ): array {
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a list."
            );
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException(
                    "Site configuration [{$context}.{$index}] must be an array."
                );
            }

            $normalized[] = $this->navigationItem(
                $item,
                $currentPath,
                "{$context}.{$index}",
            );
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function links(
        mixed $items,
        string $currentPath,
        string $context,
        array $allowedSchemes = ['http', 'https', 'mailto', 'tel'],
        bool $allowPath = true,
        bool $trackActive = true,
    ): array {
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a list."
            );
        }

        $resolved = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException(
                    "Site configuration [{$context}.{$index}] must be an array."
                );
            }

            $resolved[] = $this->link(
                $item,
                $currentPath,
                "{$context}.{$index}",
                $allowedSchemes,
                $allowPath,
                $trackActive,
            );
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $allowedSchemes
     * @return array<string, mixed>
     */
    public function link(
        array $item,
        string $currentPath,
        string $context,
        array $allowedSchemes = ['http', 'https', 'mailto', 'tel'],
        bool $allowPath = true,
        bool $trackActive = true,
    ): array {
        $this->assertOnlyKeys(
            $item,
            ['label', 'url', 'new_tab'],
            $context,
        );

        $label = $this->requiredString(
            $item['label'] ?? null,
            "{$context}.label",
        );
        $url = $this->requiredString(
            $item['url'] ?? null,
            "{$context}.url",
        );
        $newTab = $this->booleanValue(
            $item['new_tab'] ?? false,
            "{$context}.new_tab",
        );

        $this->assertUrl(
            $url,
            "{$context}.url",
            $allowedSchemes,
            $allowPath,
        );

        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'active' => $trackActive && $allowPath
                ? $this->isActiveUrl($url, $currentPath)
                : false,
            'external' => $this->isExternalUrl($url),
            'new_tab' => $newTab,
        ];
    }

    /**
     * @param list<string> $allowedSchemes
     */
    public function assertUrl(
        string $url,
        string $context,
        array $allowedSchemes,
        bool $allowPath,
    ): void {
        if (
            $allowPath
            && str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
        ) {
            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, $allowedSchemes, true)) {
            throw new InvalidArgumentException(
                "Site URL [{$context}] uses an unsupported scheme."
            );
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && trim($host) !== '') {
                return;
            }
        }

        if (
            in_array($scheme, ['mailto', 'tel'], true)
            && preg_match('/^(mailto|tel):[^\\s]+$/i', $url)
        ) {
            return;
        }

        throw new InvalidArgumentException(
            "Site URL [{$context}] is malformed."
        );
    }

    public function assertHttpOrPathUrl(
        string $url,
        string $context,
    ): void {
        $this->assertUrl(
            $url,
            $context,
            ['http', 'https'],
            true,
        );
    }

    public function isExternalUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $siteHost = strtolower((string) parse_url(
            (string) config('app.url'),
            PHP_URL_HOST,
        ));

        return $host !== ''
            && ($siteHost === '' || $host !== $siteHost);
    }

    public function normalizePath(string $path): string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);

        if (is_string($parsedPath)) {
            $path = $parsedPath;
        }

        $path = trim($path);

        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/'.trim($path, '/');
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function navigationItem(
        array $item,
        string $currentPath,
        string $context,
    ): array {
        $this->assertOnlyKeys(
            $item,
            ['label', 'url', 'children', 'new_tab'],
            $context,
        );

        $label = $this->requiredString(
            $item['label'] ?? null,
            "{$context}.label",
        );
        $url = $this->optionalString(
            $item['url'] ?? null,
            "{$context}.url",
        );
        $hasChildren = array_key_exists('children', $item);

        if ($hasChildren && $url !== null) {
            throw new InvalidArgumentException(
                "Site navigation item [{$context}] must use either [url] or [children], not both."
            );
        }

        if ($hasChildren) {
            if (array_key_exists('new_tab', $item)) {
                throw new InvalidArgumentException(
                    "Site navigation group [{$context}] may not define [new_tab]."
                );
            }

            $children = $this->navigationItems(
                $item['children'],
                $currentPath,
                "{$context}.children",
            );

            if ($children === []) {
                throw new InvalidArgumentException(
                    "Site navigation group [{$context}] must contain at least one child."
                );
            }

            return [
                'type' => 'group',
                'label' => $label,
                'active' => $this->childrenActive($children),
                'children' => $children,
            ];
        }

        if ($url === null) {
            throw new InvalidArgumentException(
                "Site navigation item [{$context}] must define [url] or [children]."
            );
        }

        return $this->link(
            [
                'label' => $label,
                'url' => $url,
                'new_tab' => $item['new_tab'] ?? false,
            ],
            $currentPath,
            $context,
        );
    }

    /**
     * @param list<array<string, mixed>> $children
     */
    private function childrenActive(array $children): bool
    {
        foreach ($children as $child) {
            if ((bool) ($child['active'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    private function isActiveUrl(
        string $url,
        string $currentPath,
    ): bool {
        if (
            ! str_starts_with($url, '/')
            || str_starts_with($url, '//')
        ) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path)
            && $this->normalizePath($path) === $currentPath;
    }

    private function requiredString(
        mixed $value,
        string $context,
    ): string {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a non-blank string."
            );
        }

        return $value;
    }

    private function optionalString(
        mixed $value,
        string $context,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function booleanValue(
        mixed $value,
        string $context,
    ): bool {
        if (! is_bool($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a boolean."
            );
        }

        return $value;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $allowedKeys
     */
    private function assertOnlyKeys(
        array $values,
        array $allowedKeys,
        string $context,
    ): void {
        $unknown = array_values(array_diff(
            array_map(
                static fn (mixed $key): string => (string) $key,
                array_keys($values),
            ),
            $allowedKeys,
        ));

        sort($unknown);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Site configuration [%s] contains unsupported key(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }
    }
}