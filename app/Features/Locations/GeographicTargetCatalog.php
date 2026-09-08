<?php

namespace App\Features\Locations;

use App\Contracts\SetupValidation\SetupValidationContributor;
use App\Support\Pages\PageRepository;
use InvalidArgumentException;

final class GeographicTargetCatalog implements SetupValidationContributor
{
    private const TYPES = [
        'state',
        'city',
        'region',
        'market',
    ];

    public function __construct(
        private readonly PageRepository $pages,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function targets(): array
    {
        $config = $this->configuration();
        $targets = $config['targets'] ?? [];

        if (! is_array($targets)) {
            throw new InvalidArgumentException(
                'Locations Feature [targets] must be an array.'
            );
        }

        if ($targets !== [] && array_is_list($targets)) {
            throw new InvalidArgumentException(
                'Locations Feature [targets] must be keyed by stable geographic target keys.'
            );
        }

        $normalized = [];

        foreach ($targets as $key => $definition) {
            if (! is_string($key) || ! $this->validKey($key)) {
                throw new InvalidArgumentException(
                    'Locations Feature contains an invalid geographic target key.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $definition,
                [
                    'type',
                    'title',
                    'summary',
                    'parent',
                    'page',
                    'service_area',
                    'physical_location',
                    'address',
                    'geography',
                    'related',
                ],
                "Locations Feature geographic target [{$key}]",
            );

            $type = $this->requiredString(
                $definition['type'] ?? null,
                "Locations Feature geographic target [{$key}.type]",
            );

            if (! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.type] must be one of: "
                    .implode(', ', self::TYPES).'.'
                );
            }

            $parent = $this->optionalTargetKey(
                $definition['parent'] ?? null,
                "Locations Feature geographic target [{$key}.parent]",
            );

            $pageKey = $this->optionalString(
                $definition['page'] ?? null,
                "Locations Feature geographic target [{$key}.page]",
            );

            $serviceArea = $this->requiredBoolean(
                $definition,
                'service_area',
                "Locations Feature geographic target [{$key}.service_area]",
            );

            $physicalLocation = $this->requiredBoolean(
                $definition,
                'physical_location',
                "Locations Feature geographic target [{$key}.physical_location]",
            );

            $address = $this->normalizeAddress(
                $definition['address'] ?? [],
                $key,
                $physicalLocation,
            );

            $normalized[$key] = [
                'key' => $key,
                'type' => $type,
                'title' => $this->requiredString(
                    $definition['title'] ?? null,
                    "Locations Feature geographic target [{$key}.title]",
                ),
                'summary' => $this->optionalString(
                    $definition['summary'] ?? null,
                    "Locations Feature geographic target [{$key}.summary]",
                ),
                'parent' => $parent,
                'page_key' => $pageKey,
                'page' => null,
                'service_area' => $serviceArea,
                'physical_location' => $physicalLocation,
                'address' => $address,
                'geography' => $this->normalizeGeography(
                    $definition['geography'] ?? [],
                    $key,
                ),
                'related' => $this->normalizeRelated(
                    $definition['related'] ?? [],
                    $key,
                ),
            ];
        }

        foreach ($normalized as $key => &$target) {
            $parent = $target['parent'];

            if ($parent !== null) {
                if ($parent === $key) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] may not be its own parent."
                    );
                }

                if (! array_key_exists($parent, $normalized)) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] references unknown parent [{$parent}]."
                    );
                }
            }

            foreach ($target['related'] as $relatedKey) {
                if ($relatedKey === $key) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] may not relate to itself."
                    );
                }

                if (! array_key_exists($relatedKey, $normalized)) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] references unknown related target [{$relatedKey}]."
                    );
                }
            }

            $pageKey = $target['page_key'];

            if ($pageKey !== null) {
                $page = $this->pages->resolveKey($pageKey);

                if ($page === null) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] references unknown page [{$pageKey}]."
                    );
                }

                $pageDefinition = $this->pages->all()[$pageKey] ?? [];
                $pageMeta = $pageDefinition['meta'] ?? [];

                $configuredIndexable = is_array($pageMeta)
                    ? ($pageMeta['indexable'] ?? config('site.seo.default_indexable', true))
                    : config('site.seo.default_indexable', true);

                if (! is_bool($configuredIndexable)) {
                    throw new InvalidArgumentException(
                        "Locations Feature geographic target [{$key}] references page [{$pageKey}] with an invalid indexable setting."
                    );
                }

                $target['page'] = [
                    'key' => $page['key'],
                    'path' => $page['path'],
                    'indexable' => $configuredIndexable,
                ];
            }
        }

        unset($target);

        $this->assertAcyclic($normalized);

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function target(string $key): array
    {
        $key = $this->requiredTargetKey(
            $key,
            'Geographic target key',
        );

        $targets = $this->targets();

        if (! array_key_exists($key, $targets)) {
            throw new InvalidArgumentException(
                "Unknown geographic target [{$key}]."
            );
        }

        return $targets[$key];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function roots(): array
    {
        return array_values(array_filter(
            $this->targets(),
            static fn (array $target): bool => $target['parent'] === null,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function children(string $parentKey): array
    {
        $parentKey = $this->requiredTargetKey(
            $parentKey,
            'Geographic target parent key',
        );

        $targets = $this->targets();

        if (! array_key_exists($parentKey, $targets)) {
            throw new InvalidArgumentException(
                "Unknown geographic target [{$parentKey}]."
            );
        }

        return $this->childrenFrom($targets, $parentKey);
    }

    /**
     * Return ancestors from the root-most ancestor to the immediate parent.
     *
     * @return list<array<string, mixed>>
     */
    public function ancestors(string $key): array
    {
        $key = $this->requiredTargetKey(
            $key,
            'Geographic target key',
        );

        $targets = $this->targets();

        if (! array_key_exists($key, $targets)) {
            throw new InvalidArgumentException(
                "Unknown geographic target [{$key}]."
            );
        }

        $ancestors = [];
        $parent = $targets[$key]['parent'];

        while ($parent !== null) {
            $ancestors[] = $targets[$parent];
            $parent = $targets[$parent]['parent'];
        }

        return array_reverse($ancestors);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function relatedTargets(string $key): array
    {
        $target = $this->target($key);
        $targets = $this->targets();

        return array_map(
            static fn (string $relatedKey): array => $targets[$relatedKey],
            $target['related'],
        );
    }

    public function pagePath(string $key): ?string
    {
        $target = $this->target($key);

        return $target['page']['path'] ?? null;
    }

    /**
     * Resolve the targets rendered by a geographic-targets section.
     *
     * `parent` renders direct children of one target.
     * `targets` renders one explicit ordered selection.
     * Supplying neither renders root targets.
     *
     * Each returned target includes its direct children so state/region cards
     * can provide deliberate internal links without generating detail pages.
     *
     * @return list<array<string, mixed>>
     */
    public function selection(
        mixed $parent = null,
        mixed $targets = null,
    ): array {
        $parent = $this->optionalTargetKey(
            $parent,
            'Geographic Targets section [parent]',
        );

        if ($parent !== null && $targets !== null) {
            throw new InvalidArgumentException(
                'Geographic Targets section may select either [parent] or [targets], not both.'
            );
        }

        $catalog = $this->targets();

        if ($targets !== null) {
            if (! is_array($targets)
                || ! array_is_list($targets)
                || $targets === []
            ) {
                throw new InvalidArgumentException(
                    'Geographic Targets section [targets] must be a non-empty list of geographic target keys.'
                );
            }

            $selected = [];
            $seen = [];

            foreach ($targets as $index => $key) {
                if (! is_string($key) || ! $this->validKey($key)) {
                    throw new InvalidArgumentException(
                        "Geographic Targets section [targets.{$index}] must be a valid geographic target key."
                    );
                }

                if (array_key_exists($key, $seen)) {
                    throw new InvalidArgumentException(
                        "Geographic Targets section [targets] contains duplicate geographic target key [{$key}]."
                    );
                }

                if (! array_key_exists($key, $catalog)) {
                    throw new InvalidArgumentException(
                        "Geographic Targets section references unknown target [{$key}]."
                    );
                }

                $seen[$key] = true;
                $selected[] = $this->presentTarget($catalog[$key], $catalog);
            }

            return $selected;
        }

        if ($parent !== null) {
            if (! array_key_exists($parent, $catalog)) {
                throw new InvalidArgumentException(
                    "Geographic Targets section references unknown parent [{$parent}]."
                );
            }

            return array_map(
                fn (array $target): array => $this->presentTarget($target, $catalog),
                $this->childrenFrom($catalog, $parent),
            );
        }

        return array_map(
            fn (array $target): array => $this->presentTarget($target, $catalog),
            array_values(array_filter(
                $catalog,
                static fn (array $target): bool => $target['parent'] === null,
            )),
        );
    }

    /**
     * @return list<string>
     */
    public function validationErrors(?string $basePath = null): array
    {
        $errors = [];

        try {
            $targets = $this->targets();
        } catch (InvalidArgumentException $exception) {
            return [$exception->getMessage()];
        }

        $pages = config('pages', []);

        if (! is_array($pages)) {
            return [];
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
                    || ($section['component'] ?? null) !== 'geographic-targets'
                ) {
                    continue;
                }

                $props = $section['props'] ?? [];

                if (! is_array($props)) {
                    continue;
                }

                $unknown = array_values(array_diff(
                    array_keys($props),
                    [
                        'eyebrow',
                        'title',
                        'intro',
                        'parent',
                        'targets',
                    ],
                ));

                if ($unknown !== []) {
                    sort($unknown);

                    $errors[] = sprintf(
                        'Page [%s] Geographic Targets section [%d] contains unsupported prop(s): %s.',
                        $pageKey,
                        $index,
                        implode(', ', $unknown),
                    );
                }

                foreach (['eyebrow', 'title', 'intro', 'parent'] as $stringProp) {
                    if (! array_key_exists($stringProp, $props)) {
                        continue;
                    }

                    $value = $props[$stringProp];

                    if ($value !== null && ! is_string($value)) {
                        $errors[] = "Page [{$pageKey}] Geographic Targets section [{$index}] [{$stringProp}] must be null or a string.";
                    }
                }

                try {
                    $selection = $this->selection(
                        $props['parent'] ?? null,
                        $props['targets'] ?? null,
                    );

                    if ($selection === []) {
                        $errors[] = "Page [{$pageKey}] Geographic Targets section [{$index}] resolves to no targets.";
                    }
                } catch (InvalidArgumentException $exception) {
                    $errors[] = "Page [{$pageKey}] Geographic Targets section [{$index}] {$exception->getMessage()}";
                }
            }
        }

        if ($targets === []) {
            return array_values(array_unique($errors));
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
            ['groups', 'items', 'targets'],
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
     * @param array<string, mixed> $targets
     */
    private function assertAcyclic(array $targets): void
    {
        $visiting = [];
        $visited = [];

        $visit = function (string $key) use (
            &$visit,
            &$visiting,
            &$visited,
            $targets,
        ): void {
            if (isset($visited[$key])) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target hierarchy contains a cycle involving [{$key}]."
                );
            }

            $visiting[$key] = true;

            $parent = $targets[$key]['parent'];

            if ($parent !== null) {
                $visit($parent);
            }

            unset($visiting[$key]);
            $visited[$key] = true;
        };

        foreach (array_keys($targets) as $key) {
            $visit($key);
        }
    }

    /**
     * @param array<string, array<string, mixed>> $targets
     * @return list<array<string, mixed>>
     */
    private function childrenFrom(
        array $targets,
        string $parentKey,
    ): array {
        return array_values(array_filter(
            $targets,
            static fn (array $target): bool => $target['parent'] === $parentKey,
        ));
    }

    /**
     * @param array<string, mixed> $target
     * @param array<string, array<string, mixed>> $targets
     * @return array<string, mixed>
     */
    private function presentTarget(
        array $target,
        array $targets,
    ): array {
        return [
            ...$target,
            'children' => $this->childrenFrom(
                $targets,
                $target['key'],
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeAddress(
        mixed $address,
        string $key,
        bool $physicalLocation,
    ): array {
        if ($address === null) {
            return [];
        }

        if (! is_array($address) || ! array_is_list($address)) {
            throw new InvalidArgumentException(
                "Locations Feature geographic target [{$key}.address] must be null or a list of address lines."
            );
        }

        $normalized = [];

        foreach ($address as $index => $line) {
            if (! is_string($line) || trim($line) === '') {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.address.{$index}] must be a non-blank string."
                );
            }

            $normalized[] = trim($line);
        }

        if (! $physicalLocation && $normalized !== []) {
            throw new InvalidArgumentException(
                "Locations Feature geographic target [{$key}] may include address lines only when [physical_location] is true."
            );
        }

        return $normalized;
    }

    /**
     * @return array{
     *     country_code: string|null,
     *     state_code: string|null,
     *     county: string|null,
     *     region: string|null,
     *     metro: string|null
     * }
     */
    private function normalizeGeography(
        mixed $geography,
        string $key,
    ): array {
        if ($geography === null) {
            $geography = [];
        }

        if (! is_array($geography)
            || ($geography !== [] && array_is_list($geography))
        ) {
            throw new InvalidArgumentException(
                "Locations Feature geographic target [{$key}.geography] must be an associative array."
            );
        }

        $this->assertOnlyKeys(
            $geography,
            [
                'country_code',
                'state_code',
                'county',
                'region',
                'metro',
            ],
            "Locations Feature geographic target [{$key}.geography]",
        );

        $countryCode = $this->optionalString(
            $geography['country_code'] ?? null,
            "Locations Feature geographic target [{$key}.geography.country_code]",
        );

        if ($countryCode !== null) {
            $countryCode = strtoupper($countryCode);

            if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.geography.country_code] must use a two-letter country code."
                );
            }
        }

        $stateCode = $this->optionalString(
            $geography['state_code'] ?? null,
            "Locations Feature geographic target [{$key}.geography.state_code]",
        );

        if ($stateCode !== null) {
            $stateCode = strtoupper($stateCode);

            if (preg_match('/^[A-Z0-9][A-Z0-9-]{0,9}$/', $stateCode) !== 1) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.geography.state_code] must use a short letters/numbers/hyphen code."
                );
            }
        }

        return [
            'country_code' => $countryCode,
            'state_code' => $stateCode,
            'county' => $this->optionalString(
                $geography['county'] ?? null,
                "Locations Feature geographic target [{$key}.geography.county]",
            ),
            'region' => $this->optionalString(
                $geography['region'] ?? null,
                "Locations Feature geographic target [{$key}.geography.region]",
            ),
            'metro' => $this->optionalString(
                $geography['metro'] ?? null,
                "Locations Feature geographic target [{$key}.geography.metro]",
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function normalizeRelated(
        mixed $related,
        string $key,
    ): array {
        if (! is_array($related) || ! array_is_list($related)) {
            throw new InvalidArgumentException(
                "Locations Feature geographic target [{$key}.related] must be a list."
            );
        }

        $normalized = [];
        $seen = [];

        foreach ($related as $index => $relatedKey) {
            if (! is_string($relatedKey) || ! $this->validKey($relatedKey)) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.related.{$index}] must be a valid geographic target key."
                );
            }

            if (isset($seen[$relatedKey])) {
                throw new InvalidArgumentException(
                    "Locations Feature geographic target [{$key}.related] contains duplicate target [{$relatedKey}]."
                );
            }

            $seen[$relatedKey] = true;
            $normalized[] = $relatedKey;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function requiredBoolean(
        array $definition,
        string $key,
        string $context,
    ): bool {
        if (! array_key_exists($key, $definition)
            || ! is_bool($definition[$key])
        ) {
            throw new InvalidArgumentException(
                "{$context} must be explicitly configured as a boolean."
            );
        }

        return $definition[$key];
    }

    private function requiredTargetKey(
        string $value,
        string $context,
    ): string {
        $value = trim($value);

        if (! $this->validKey($value)) {
            throw new InvalidArgumentException(
                "{$context} must use lowercase letters, numbers, hyphens, or underscores."
            );
        }

        return $value;
    }

    private function optionalTargetKey(
        mixed $value,
        string $context,
    ): ?string {
        $value = $this->optionalString($value, $context);

        if ($value === null) {
            return null;
        }

        if (! $this->validKey($value)) {
            throw new InvalidArgumentException(
                "{$context} must use lowercase letters, numbers, hyphens, or underscores."
            );
        }

        return $value;
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