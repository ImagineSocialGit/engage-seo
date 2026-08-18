<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class SitePresentationResolver
{
    /**
     * @var array<string, array<string, string>>
     */
    private const THEME_VARIABLES = [
        'colors' => [
            'background' => '--site-color-background',
            'surface' => '--site-color-surface',
            'text' => '--site-color-text',
            'muted' => '--site-color-muted',
            'primary' => '--site-color-primary',
            'primary_contrast' => '--site-color-primary-contrast',
            'border' => '--site-color-border',
            'focus' => '--site-color-focus',
        ],
        'typography' => [
            'body_font_family' => '--site-font-body',
            'heading_font_family' => '--site-font-heading',
        ],
        'layout' => [
            'content_max_width' => '--site-content-max-width',
        ],
        'radius' => [
            'control' => '--site-radius-control',
            'surface' => '--site-radius-surface',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function resolve(?string $currentPath = null): array
    {
        $currentPath = $this->normalizeCurrentPath($currentPath ?? '/');

        return [
            ...$this->identity(),
            'shell' => $this->shell($currentPath),
            'theme' => $this->theme(),
        ];
    }

    /**
     * @return list<string>
     */
    public function validationErrors(): array
    {
        $errors = [];

        foreach ([
            fn (): array => $this->identity(),
            fn (): array => $this->shell('/'),
            fn (): array => $this->theme(),
        ] as $check) {
            try {
                $check();
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(): array
    {
        $siteName = $this->optionalString(
            config('site.name'),
            'site.name',
        );

        $clientName = $this->optionalString(
            config('client.name'),
            'client.name',
        );

        $applicationName = $this->optionalString(
            config('app.name'),
            'app.name',
        );

        $name = $siteName ?? $clientName ?? $applicationName;

        if ($name === null) {
            throw new InvalidArgumentException(
                'Site presentation requires a non-blank site, client, or application name.'
            );
        }

        $brand = config('site.brand', []);

        if (! is_array($brand)) {
            throw new InvalidArgumentException(
                'Site configuration [brand] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $brand,
            ['logo', 'logo_alt'],
            'site.brand',
        );

        $logo = $this->optionalString(
            $brand['logo'] ?? null,
            'site.brand.logo',
        );

        if ($logo !== null) {
            $this->assertAssetUrl($logo, 'site.brand.logo');
        }

        $logoAlt = $this->optionalString(
            $brand['logo_alt'] ?? null,
            'site.brand.logo_alt',
        ) ?? $name;

        return [
            'name' => $name,
            'brand' => [
                'logo' => $logo,
                'logo_alt' => $logoAlt,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shell(string $currentPath): array
    {
        $shell = config('site.shell', []);

        if (! is_array($shell)) {
            throw new InvalidArgumentException(
                'Site configuration [shell] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $shell,
            ['header', 'navigation', 'footer'],
            'site.shell',
        );

        $header = $shell['header'] ?? [];
        $navigation = $shell['navigation'] ?? [];
        $footer = $shell['footer'] ?? [];

        if (! is_array($header)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.header] must be an array.'
            );
        }

        if (! is_array($navigation)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.navigation] must be an array.'
            );
        }

        if (! is_array($footer)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.footer] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $header,
            ['enabled'],
            'site.shell.header',
        );

        $this->assertOnlyKeys(
            $navigation,
            ['enabled', 'items', 'primary_cta'],
            'site.shell.navigation',
        );

        $this->assertOnlyKeys(
            $footer,
            ['enabled', 'items'],
            'site.shell.footer',
        );

        $primaryCta = $navigation['primary_cta'] ?? null;

        if ($primaryCta !== null && ! is_array($primaryCta)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.navigation.primary_cta] must be null or an array.'
            );
        }

        return [
            'header' => [
                'enabled' => $this->booleanValue(
                    $header['enabled'] ?? true,
                    'site.shell.header.enabled',
                ),
            ],
            'navigation' => [
                'enabled' => $this->booleanValue(
                    $navigation['enabled'] ?? true,
                    'site.shell.navigation.enabled',
                ),
                'items' => $this->navigationItems(
                    $navigation['items'] ?? [],
                    $currentPath,
                    'site.shell.navigation.items',
                ),
                'primary_cta' => $primaryCta === null
                    ? null
                    : $this->navigationLink(
                        $primaryCta,
                        $currentPath,
                        'site.shell.navigation.primary_cta',
                    ),
            ],
            'footer' => [
                'enabled' => $this->booleanValue(
                    $footer['enabled'] ?? true,
                    'site.shell.footer.enabled',
                ),
                'items' => $this->navigationItems(
                    $footer['items'] ?? [],
                    $currentPath,
                    'site.shell.footer.items',
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function theme(): array
    {
        $theme = config('site.theme', []);

        if (! is_array($theme)) {
            throw new InvalidArgumentException(
                'Site configuration [theme] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $theme,
            array_keys(self::THEME_VARIABLES),
            'site.theme',
        );

        $resolved = [];
        $cssVariables = [];

        foreach (self::THEME_VARIABLES as $group => $tokens) {
            $values = $theme[$group] ?? null;

            if (! is_array($values)) {
                throw new InvalidArgumentException(
                    "Site configuration [theme.{$group}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $values,
                array_keys($tokens),
                "site.theme.{$group}",
            );

            $resolved[$group] = [];

            foreach ($tokens as $token => $cssVariable) {
                if (! array_key_exists($token, $values)) {
                    throw new InvalidArgumentException(
                        "Site theme token [{$group}.{$token}] is missing."
                    );
                }

                $value = $this->cssValue(
                    $values[$token],
                    "site.theme.{$group}.{$token}",
                );

                $resolved[$group][$token] = $value;
                $cssVariables[$cssVariable] = $value;
            }
        }

        return [
            ...$resolved,
            'css_variables' => $cssVariables,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navigationItems(
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
            ['label', 'url', 'children'],
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

            $active = false;

            foreach ($children as $child) {
                if ((bool) ($child['active'] ?? false)) {
                    $active = true;

                    break;
                }
            }

            return [
                'type' => 'group',
                'label' => $label,
                'active' => $active,
                'children' => $children,
            ];
        }

        if ($url === null) {
            throw new InvalidArgumentException(
                "Site navigation item [{$context}] must define [url] or [children]."
            );
        }

        return $this->resolvedLink(
            $label,
            $url,
            $currentPath,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function navigationLink(
        array $item,
        string $currentPath,
        string $context,
    ): array {
        $this->assertOnlyKeys(
            $item,
            ['label', 'url'],
            $context,
        );

        return $this->resolvedLink(
            $this->requiredString(
                $item['label'] ?? null,
                "{$context}.label",
            ),
            $this->requiredString(
                $item['url'] ?? null,
                "{$context}.url",
            ),
            $currentPath,
            $context,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedLink(
        string $label,
        string $url,
        string $currentPath,
        string $context,
    ): array {
        $this->assertNavigationUrl($url, "{$context}.url");

        return [
            'type' => 'link',
            'label' => $label,
            'url' => $url,
            'active' => $this->isActiveUrl($url, $currentPath),
        ];
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

        if (! is_string($path)) {
            return false;
        }

        return $this->normalizeCurrentPath($path) === $currentPath;
    }

    private function assertNavigationUrl(
        string $url,
        string $context,
    ): void {
        if (
            str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
        ) {
            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && trim($host) !== '') {
                return;
            }
        }

        if (
            in_array($scheme, ['mailto', 'tel'], true)
            && preg_match('/^(mailto|tel):[^\s]+$/i', $url)
        ) {
            return;
        }

        throw new InvalidArgumentException(
            "Site navigation URL [{$context}] must be an absolute path or a valid http, https, mailto, or tel URL."
        );
    }

    private function assertAssetUrl(
        string $url,
        string $context,
    ): void {
        if (
            str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
        ) {
            return;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (
            in_array($scheme, ['http', 'https'], true)
            && is_string($host)
            && trim($host) !== ''
        ) {
            return;
        }

        throw new InvalidArgumentException(
            "Site asset URL [{$context}] must be an absolute path or a valid http/https URL."
        );
    }

    private function normalizeCurrentPath(string $path): string
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

        return $value !== ''
            ? $value
            : null;
    }

    private function cssValue(
        mixed $value,
        string $context,
    ): string {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException(
                "Site theme token [{$context}] must be a non-blank string."
            );
        }

        $value = trim($value);

        if (preg_match('/[{};<>\r\n]/', $value)) {
            throw new InvalidArgumentException(
                "Site theme token [{$context}] contains unsupported CSS characters."
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