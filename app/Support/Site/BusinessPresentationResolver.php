<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class BusinessPresentationResolver
{
    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $currentPath): array
    {
        $business = config('site.business', []);

        if (! is_array($business)) {
            throw new InvalidArgumentException(
                'Site configuration [business] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $business,
            ['phone', 'email', 'address', 'social_links'],
            'site.business',
        );

        return [
            'phone' => $this->contactMethod(
                $business['phone'] ?? null,
                'tel',
                'site.business.phone',
            ),
            'email' => $this->contactMethod(
                $business['email'] ?? null,
                'mailto',
                'site.business.email',
            ),
            'address' => $this->address(
                $business['address'] ?? [],
            ),
            'social_links' => $this->links->links(
                $business['social_links'] ?? [],
                $currentPath,
                'site.business.social_links',
                ['http', 'https'],
                false,
                false,
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function contactMethod(
        mixed $value,
        string $requiredScheme,
        string $context,
    ): ?array {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException(
                "Site configuration [{$context}] must be null or an array."
            );
        }

        $this->assertOnlyKeys(
            $value,
            ['label', 'value', 'url', 'new_tab'],
            $context,
        );

        $label = $this->requiredString(
            $value['label'] ?? null,
            "{$context}.label",
        );
        $displayValue = $this->requiredString(
            $value['value'] ?? null,
            "{$context}.value",
        );
        $url = $this->requiredString(
            $value['url'] ?? null,
            "{$context}.url",
        );
        $newTab = $this->booleanValue(
            $value['new_tab'] ?? false,
            "{$context}.new_tab",
        );

        $this->links->assertUrl(
            $url,
            "{$context}.url",
            [$requiredScheme],
            false,
        );

        return [
            'label' => $label,
            'value' => $displayValue,
            'url' => $url,
            'new_tab' => $newTab,
            'external' => false,
        ];
    }

    /**
     * @return array{lines: list<string>, url: string|null, new_tab: bool, external: bool}
     */
    private function address(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException(
                'Site configuration [site.business.address] must be an array.'
            );
        }

        $this->assertOnlyKeys(
            $value,
            ['lines', 'url', 'new_tab'],
            'site.business.address',
        );

        $lines = $this->stringList(
            $value['lines'] ?? [],
            'site.business.address.lines',
        );
        $url = $this->optionalString(
            $value['url'] ?? null,
            'site.business.address.url',
        );
        $newTab = $this->booleanValue(
            $value['new_tab'] ?? false,
            'site.business.address.new_tab',
        );

        if ($url === null && $newTab) {
            throw new InvalidArgumentException(
                'Site business address [new_tab] requires an address [url].'
            );
        }

        if ($url !== null) {
            $this->links->assertUrl(
                $url,
                'site.business.address.url',
                ['http', 'https'],
                true,
            );
        }

        return [
            'lines' => $lines,
            'url' => $url,
            'new_tab' => $newTab,
            'external' => $url !== null
                ? $this->links->isExternalUrl($url)
                : false,
        ];
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