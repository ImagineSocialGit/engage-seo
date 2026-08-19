<?php

namespace App\Features\Locations;

use App\Contracts\SetupValidation\SetupValidationContributor;
use App\Support\Site\SiteLinkNormalizer;
use InvalidArgumentException;

final class LocationCatalog implements SetupValidationContributor
{
    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     intro: string|null
     * }>
     */
    public function groups(): array
    {
        $config = $this->configuration();
        $groups = $config['groups'] ?? [];

        if (! is_array($groups)) {
            throw new InvalidArgumentException(
                'Locations Feature [groups] must be an array.'
            );
        }

        if ($groups !== [] && array_is_list($groups)) {
            throw new InvalidArgumentException(
                'Locations Feature [groups] must be keyed by stable group keys.'
            );
        }

        $normalized = [];

        foreach ($groups as $key => $definition) {
            if (! is_string($key) || ! $this->validKey($key)) {
                throw new InvalidArgumentException(
                    'Locations Feature contains an invalid group key.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Locations Feature group [{$key}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $definition,
                ['title', 'intro'],
                "Locations Feature group [{$key}]",
            );

            $normalized[$key] = [
                'key' => $key,
                'title' => $this->requiredString(
                    $definition['title'] ?? null,
                    "Locations Feature group [{$key}.title]",
                ),
                'intro' => $this->optionalString(
                    $definition['intro'] ?? null,
                    "Locations Feature group [{$key}.intro]",
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     title: string,
     *     summary: string|null,
     *     group: string|null,
     *     address: list<string>,
     *     image: array<string, mixed>|null,
     *     facts: list<array{label: string, value: string}>,
     *     links: list<array<string, mixed>>
     * }>
     */
    public function items(): array
    {
        $config = $this->configuration();
        $items = $config['items'] ?? [];

        if (! is_array($items)) {
            throw new InvalidArgumentException(
                'Locations Feature [items] must be an array.'
            );
        }

        if ($items !== [] && array_is_list($items)) {
            throw new InvalidArgumentException(
                'Locations Feature [items] must be keyed by stable location keys.'
            );
        }

        $groups = $this->groups();
        $normalized = [];

        foreach ($items as $key => $definition) {
            if (! is_string($key) || ! $this->validKey($key)) {
                throw new InvalidArgumentException(
                    'Locations Feature contains an invalid location key.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Locations Feature item [{$key}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $definition,
                [
                    'title',
                    'summary',
                    'group',
                    'address',
                    'image',
                    'facts',
                    'links',
                ],
                "Locations Feature item [{$key}]",
            );

            $group = $this->optionalString(
                $definition['group'] ?? null,
                "Locations Feature item [{$key}.group]",
            );

            if ($groups !== [] && $group === null) {
                throw new InvalidArgumentException(
                    "Locations Feature item [{$key}] must reference a configured group."
                );
            }

            if ($group !== null && ! array_key_exists($group, $groups)) {
                throw new InvalidArgumentException(
                    "Locations Feature item [{$key}] references unknown group [{$group}]."
                );
            }

            $normalized[$key] = [
                'key' => $key,
                'title' => $this->requiredString(
                    $definition['title'] ?? null,
                    "Locations Feature item [{$key}.title]",
                ),
                'summary' => $this->optionalString(
                    $definition['summary'] ?? null,
                    "Locations Feature item [{$key}.summary]",
                ),
                'group' => $group,
                'address' => $this->normalizeAddress(
                    $definition['address'] ?? [],
                    $key,
                ),
                'image' => $this->normalizeImage(
                    $definition['image'] ?? null,
                    $key,
                ),
                'facts' => $this->normalizeFacts(
                    $definition['facts'] ?? [],
                    $key,
                ),
                'links' => $this->normalizeLinks(
                    $definition['links'] ?? [],
                    $key,
                ),
            ];
        }

        return $normalized;
    }

    /**
     * Resolve the catalog buckets rendered by a Locations section.
     *
     * `group` renders one configured group.
     * `items` renders one explicit ordered selection across groups.
     * Supplying neither renders the complete catalog.
     *
     * @return list<array{
     *     key: string|null,
     *     title: string|null,
     *     intro: string|null,
     *     items: list<array<string, mixed>>
     * }>
     */
    public function selection(
        mixed $group = null,
        mixed $items = null,
    ): array {
        $group = $this->optionalString(
            $group,
            'Locations section [group]',
        );

        if ($group !== null && $items !== null) {
            throw new InvalidArgumentException(
                'Locations section may select either [group] or [items], not both.'
            );
        }

        $catalogItems = $this->items();
        $groups = $this->groups();

        if ($items !== null) {
            if (! is_array($items) || ! array_is_list($items) || $items === []) {
                throw new InvalidArgumentException(
                    'Locations section [items] must be a non-empty list of location keys.'
                );
            }

            $selected = [];
            $seen = [];

            foreach ($items as $index => $key) {
                if (! is_string($key) || ! $this->validKey($key)) {
                    throw new InvalidArgumentException(
                        "Locations section [items.{$index}] must be a valid location key."
                    );
                }

                if (array_key_exists($key, $seen)) {
                    throw new InvalidArgumentException(
                        "Locations section [items] contains duplicate location key [{$key}]."
                    );
                }

                if (! array_key_exists($key, $catalogItems)) {
                    throw new InvalidArgumentException(
                        "Locations section references unknown location [{$key}]."
                    );
                }

                $seen[$key] = true;
                $selected[] = $catalogItems[$key];
            }

            return [[
                'key' => null,
                'title' => null,
                'intro' => null,
                'items' => $selected,
            ]];
        }

        if ($group !== null) {
            if (! array_key_exists($group, $groups)) {
                throw new InvalidArgumentException(
                    "Locations section references unknown group [{$group}]."
                );
            }

            $selected = array_values(array_filter(
                $catalogItems,
                static fn (array $item): bool => $item['group'] === $group,
            ));

            if ($selected === []) {
                throw new InvalidArgumentException(
                    "Locations section group [{$group}] contains no locations."
                );
            }

            return [[
                ...$groups[$group],
                'items' => $selected,
            ]];
        }

        if ($groups === []) {
            return [[
                'key' => null,
                'title' => null,
                'intro' => null,
                'items' => array_values($catalogItems),
            ]];
        }

        $buckets = [];

        foreach ($groups as $key => $definition) {
            $selected = array_values(array_filter(
                $catalogItems,
                static fn (array $item): bool => $item['group'] === $key,
            ));

            $buckets[] = [
                ...$definition,
                'items' => $selected,
            ];
        }

        return $buckets;
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array
    {
        $errors = [];

        try {
            $groups = $this->groups();
            $items = $this->items();
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }

        if ($items === []) {
            $errors[] = 'Locations Feature is enabled but has no configured location items.';
        }

        if ($groups !== []) {
            foreach ($groups as $groupKey => $group) {
                $hasItems = false;

                foreach ($items as $item) {
                    if ($item['group'] === $groupKey) {
                        $hasItems = true;

                        break;
                    }
                }

                if (! $hasItems) {
                    $errors[] = "Locations Feature group [{$groupKey}] contains no location items.";
                }
            }
        }

        $pages = config('pages', []);

        if (! is_array($pages)) {
            return array_values(array_unique($errors));
        }

        foreach ($pages as $pageKey => $page) {
            if (! is_string($pageKey) || ! is_array($page)) {
                continue;
            }

            $sections = $page['sections'] ?? [];

            if (! is_array($sections) || ! array_is_list($sections)) {
                continue;
            }

            foreach ($sections as $index => $section) {
                if (! is_array($section)
                    || ($section['component'] ?? null) !== 'locations'
                ) {
                    continue;
                }

                $props = $section['props'] ?? [];

                if (! is_array($props)) {
                    continue;
                }

                $unknown = array_values(array_diff(
                    array_keys($props),
                    ['eyebrow', 'title', 'intro', 'group', 'items'],
                ));

                if ($unknown !== []) {
                    sort($unknown);

                    $errors[] = sprintf(
                        'Page [%s] Locations section [%d] contains unsupported prop(s): %s.',
                        $pageKey,
                        $index,
                        implode(', ', $unknown),
                    );
                }

                foreach (['eyebrow', 'title', 'intro'] as $stringProp) {
                    if (! array_key_exists($stringProp, $props)) {
                        continue;
                    }

                    $value = $props[$stringProp];

                    if ($value !== null && ! is_string($value)) {
                        $errors[] = "Page [{$pageKey}] Locations section [{$index}] [{$stringProp}] must be null or a string.";
                    }
                }

                try {
                    $this->selection(
                        $props['group'] ?? null,
                        $props['items'] ?? null,
                    );
                } catch (InvalidArgumentException $exception) {
                    $errors[] = "Page [{$pageKey}] Locations section [{$index}] {$exception->getMessage()}";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        $config = config('features.locations', []);

        if (! is_array($config)) {
            throw new InvalidArgumentException(
                'Locations Feature configuration must be an array.'
            );
        }

        $unknown = array_values(array_diff(
            array_keys($config),
            ['groups', 'items'],
        ));

        if ($unknown !== []) {
            sort($unknown);

            throw new InvalidArgumentException(
                'Locations Feature configuration contains unsupported key(s): '
                .implode(', ', $unknown).'.'
            );
        }

        return $config;
    }

    /**
     * @return list<string>
     */
    private function normalizeAddress(
        mixed $address,
        string $itemKey,
    ): array {
        if ($address === null) {
            return [];
        }

        if (! is_array($address) || ! array_is_list($address)) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.address] must be null or a list of address lines."
            );
        }

        $normalized = [];

        foreach ($address as $index => $line) {
            if (! is_string($line) || trim($line) === '') {
                throw new InvalidArgumentException(
                    "Locations Feature item [{$itemKey}.address.{$index}] must be a non-blank string."
                );
            }

            $normalized[] = trim($line);
        }

        return $normalized;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function normalizeFacts(
        mixed $facts,
        string $itemKey,
    ): array {
        if (! is_array($facts) || ! array_is_list($facts)) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.facts] must be a list."
            );
        }

        $normalized = [];

        foreach ($facts as $index => $fact) {
            if (! is_array($fact)) {
                throw new InvalidArgumentException(
                    "Locations Feature item [{$itemKey}.facts.{$index}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $fact,
                ['label', 'value'],
                "Locations Feature item [{$itemKey}.facts.{$index}]",
            );

            $normalized[] = [
                'label' => $this->requiredString(
                    $fact['label'] ?? null,
                    "Locations Feature item [{$itemKey}.facts.{$index}.label]",
                ),
                'value' => $this->requiredString(
                    $fact['value'] ?? null,
                    "Locations Feature item [{$itemKey}.facts.{$index}.value]",
                ),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeImage(
        mixed $image,
        string $itemKey,
    ): ?array {
        if ($image === null) {
            return null;
        }

        if (! is_array($image)) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.image] must be null or an array."
            );
        }

        $this->assertOnlyKeys(
            $image,
            [
                'asset',
                'alt',
                'sizes',
                'loading',
                'fetchpriority',
            ],
            "Locations Feature item [{$itemKey}.image]",
        );

        $asset = $this->requiredString(
            $image['asset'] ?? null,
            "Locations Feature item [{$itemKey}.image.asset]",
        );

        if (! array_key_exists('alt', $image) || ! is_string($image['alt'])) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.image.alt] must be explicitly provided as a string."
            );
        }

        $sizes = $this->optionalString(
            $image['sizes'] ?? '100vw',
            "Locations Feature item [{$itemKey}.image.sizes]",
        );

        if ($sizes === null) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.image.sizes] must not be blank."
            );
        }

        $loading = $this->optionalString(
            $image['loading'] ?? 'lazy',
            "Locations Feature item [{$itemKey}.image.loading]",
        );

        if (! in_array($loading, ['lazy', 'eager'], true)) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.image.loading] must be lazy or eager."
            );
        }

        $fetchpriority = $this->optionalString(
            $image['fetchpriority'] ?? 'auto',
            "Locations Feature item [{$itemKey}.image.fetchpriority]",
        );

        if (! in_array($fetchpriority, ['auto', 'high', 'low'], true)) {
            throw new InvalidArgumentException(
                "Locations Feature item [{$itemKey}.image.fetchpriority] must be auto, high, or low."
            );
        }

        return [
            'asset' => $asset,
            'alt' => $image['alt'],
            'sizes' => $sizes,
            'loading' => $loading,
            'fetchpriority' => $fetchpriority,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeLinks(
        mixed $links,
        string $itemKey,
    ): array {
        return $this->links->links(
            $links,
            '/',
            "features.locations.items.{$itemKey}.links",
            ['http', 'https', 'mailto', 'tel'],
            true,
            false,
        );
    }

    private function validKey(string $key): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) === 1;
    }

    private function requiredString(
        mixed $value,
        string $context,
    ): string {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            throw new InvalidArgumentException(
                "{$context} must be a non-blank string."
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
                "{$context} must be null or a string."
            );
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
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
                '%s contains unsupported key(s): %s.',
                $context,
                implode(', ', $unknown),
            ));
        }
    }
}