<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class SiteShellResolver
{
    /** @var list<string> */
    private const THEMES = [
        'default',
        'inverse',
    ];

    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $currentPath): array
    {
        $shell = config('site.shell', []);

        if (! is_array($shell)) {
            throw new InvalidArgumentException(
                'Site configuration [shell] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $shell,
            ['utility_bar', 'header', 'navigation', 'footer'],
            'site.shell',
        );

        $utilityBar = $this->arrayValue(
            $shell['utility_bar'] ?? [],
            'site.shell.utility_bar',
        );
        $header = $this->arrayValue(
            $shell['header'] ?? [],
            'site.shell.header',
        );
        $navigation = $this->arrayValue(
            $shell['navigation'] ?? [],
            'site.shell.navigation',
        );
        $footer = $this->arrayValue(
            $shell['footer'] ?? [],
            'site.shell.footer',
        );

        $this->assertOnlyKeys(
            $utilityBar,
            ['enabled', 'theme', 'items'],
            'site.shell.utility_bar',
        );
        $this->assertOnlyKeys(
            $header,
            ['enabled', 'theme'],
            'site.shell.header',
        );
        $this->assertOnlyKeys(
            $navigation,
            ['enabled', 'items', 'primary_cta'],
            'site.shell.navigation',
        );
        $this->assertOnlyKeys(
            $footer,
            ['enabled', 'theme', 'intro', 'groups', 'cta', 'legal'],
            'site.shell.footer',
        );

        $primaryCta = $navigation['primary_cta'] ?? null;

        if ($primaryCta !== null && ! is_array($primaryCta)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.navigation.primary_cta] must be null or an array.'
            );
        }

        return [
            'utility_bar' => [
                'enabled' => $this->booleanValue(
                    $utilityBar['enabled'] ?? false,
                    'site.shell.utility_bar.enabled',
                ),
                'theme' => $this->theme(
                    $utilityBar['theme'] ?? 'inverse',
                    'site.shell.utility_bar.theme',
                ),
                'items' => $this->utilityItems(
                    $utilityBar['items'] ?? [],
                    $currentPath,
                ),
            ],
            'header' => [
                'enabled' => $this->booleanValue(
                    $header['enabled'] ?? true,
                    'site.shell.header.enabled',
                ),
                'theme' => $this->theme(
                    $header['theme'] ?? 'default',
                    'site.shell.header.theme',
                ),
            ],
            'navigation' => [
                'enabled' => $this->booleanValue(
                    $navigation['enabled'] ?? true,
                    'site.shell.navigation.enabled',
                ),
                'items' => $this->links->navigationItems(
                    $navigation['items'] ?? [],
                    $currentPath,
                    'site.shell.navigation.items',
                ),
                'primary_cta' => $primaryCta === null
                    ? null
                    : $this->links->link(
                        $primaryCta,
                        $currentPath,
                        'site.shell.navigation.primary_cta',
                    ),
            ],
            'footer' => $this->footer($footer, $currentPath),
        ];
    }

    /**
     * @param array<string, mixed> $footer
     * @return array<string, mixed>
     */
    private function footer(array $footer, string $currentPath): array
    {
        $cta = $footer['cta'] ?? null;
        $legal = $this->arrayValue(
            $footer['legal'] ?? [],
            'site.shell.footer.legal',
        );

        if ($cta !== null && ! is_array($cta)) {
            throw new InvalidArgumentException(
                'Site configuration [shell.footer.cta] must be null or an array.'
            );
        }

        $this->assertOnlyKeys(
            $legal,
            ['lines', 'links'],
            'site.shell.footer.legal',
        );

        return [
            'enabled' => $this->booleanValue(
                $footer['enabled'] ?? true,
                'site.shell.footer.enabled',
            ),
            'theme' => $this->theme(
                $footer['theme'] ?? 'default',
                'site.shell.footer.theme',
            ),
            'intro' => $this->optionalString(
                $footer['intro'] ?? null,
                'site.shell.footer.intro',
            ),
            'groups' => $this->footerGroups(
                $footer['groups'] ?? [],
                $currentPath,
            ),
            'cta' => $cta === null
                ? null
                : $this->footerCta($cta, $currentPath),
            'legal' => [
                'lines' => $this->stringList(
                    $legal['lines'] ?? [],
                    'site.shell.footer.legal.lines',
                ),
                'links' => $this->links->links(
                    $legal['links'] ?? [],
                    $currentPath,
                    'site.shell.footer.legal.links',
                ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $cta
     * @return array<string, mixed>
     */
    private function footerCta(array $cta, string $currentPath): array
    {
        $this->assertOnlyKeys(
            $cta,
            ['title', 'description', 'actions'],
            'site.shell.footer.cta',
        );

        $actions = $this->links->links(
            $cta['actions'] ?? [],
            $currentPath,
            'site.shell.footer.cta.actions',
        );

        if ($actions === []) {
            throw new InvalidArgumentException(
                'Site footer CTA must contain at least one action.'
            );
        }

        return [
            'title' => $this->requiredString(
                $cta['title'] ?? null,
                'site.shell.footer.cta.title',
            ),
            'description' => $this->optionalString(
                $cta['description'] ?? null,
                'site.shell.footer.cta.description',
            ),
            'actions' => $actions,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function footerGroups(mixed $groups, string $currentPath): array
    {
        if (! is_array($groups) || ! array_is_list($groups)) {
            throw new InvalidArgumentException(
                'Site configuration [site.shell.footer.groups] must be a list.'
            );
        }

        $resolved = [];

        foreach ($groups as $index => $group) {
            $context = "site.shell.footer.groups.{$index}";
            $group = $this->arrayValue($group, $context);

            $this->assertOnlyKeys(
                $group,
                ['label', 'items'],
                $context,
            );

            $items = $this->links->links(
                $group['items'] ?? [],
                $currentPath,
                "{$context}.items",
            );

            if ($items === []) {
                throw new InvalidArgumentException(
                    "Site footer group [{$context}] must contain at least one link."
                );
            }

            $resolved[] = [
                'label' => $this->requiredString(
                    $group['label'] ?? null,
                    "{$context}.label",
                ),
                'items' => $items,
            ];
        }

        return $resolved;
    }

    /** @return list<array<string, mixed>> */
    private function utilityItems(mixed $items, string $currentPath): array
    {
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException(
                'Site configuration [site.shell.utility_bar.items] must be a list.'
            );
        }

        $resolved = [];

        foreach ($items as $index => $item) {
            $context = "site.shell.utility_bar.items.{$index}";
            $item = $this->arrayValue($item, $context);
            $hasText = array_key_exists('text', $item);
            $hasLink = array_key_exists('label', $item)
                || array_key_exists('url', $item);

            if ($hasText && $hasLink) {
                throw new InvalidArgumentException(
                    "Site utility item [{$context}] must be text or a link, not both."
                );
            }

            if ($hasText) {
                $this->assertOnlyKeys($item, ['text'], $context);

                $resolved[] = [
                    'type' => 'text',
                    'text' => $this->requiredString(
                        $item['text'] ?? null,
                        "{$context}.text",
                    ),
                ];

                continue;
            }

            $resolved[] = $this->links->link(
                $item,
                $currentPath,
                $context,
            );
        }

        return $resolved;
    }

    private function theme(mixed $value, string $context): string
    {
        $theme = $this->requiredString($value, $context);

        if (! in_array($theme, self::THEMES, true)) {
            throw new InvalidArgumentException(
                "Site shell theme [{$context}] must be one of: ".implode(', ', self::THEMES).'.'
            );
        }

        return $theme;
    }

    /** @return list<string> */
    private function stringList(mixed $value, string $context): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a list."
            );
        }

        $resolved = [];

        foreach ($value as $index => $item) {
            $resolved[] = $this->requiredString(
                $item,
                "{$context}.{$index}",
            );
        }

        return $resolved;
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value, string $context): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be an array."
            );
        }

        return $value;
    }

    private function requiredString(mixed $value, string $context): string
    {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be a non-blank string."
            );
        }

        return $value;
    }

    private function optionalString(mixed $value, string $context): ?string
    {
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

    private function booleanValue(mixed $value, string $context): bool
    {
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