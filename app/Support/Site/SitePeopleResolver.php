<?php

namespace App\Support\Site;

use InvalidArgumentException;

final class SitePeopleResolver
{
    public function __construct(
        private readonly SiteLinkNormalizer $links,
    ) {
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     url: string|null,
     *     job_title: string|null,
     *     description: string|null,
     *     same_as: list<string>
     * }>
     */
    public function all(): array
    {
        $people = config('site.people', []);

        if (! is_array($people)) {
            throw new InvalidArgumentException(
                'Site configuration [people] must be an array.'
            );
        }

        if ($people !== [] && array_is_list($people)) {
            throw new InvalidArgumentException(
                'Site configuration [people] must be keyed by stable person keys.'
            );
        }

        $resolved = [];

        foreach ($people as $key => $definition) {
            if (! is_string($key)
                || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Site configuration [people] contains an invalid person key.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Site configuration [people.{$key}] must be an array."
                );
            }

            $this->assertOnlyKeys(
                $definition,
                ['name', 'url', 'job_title', 'description', 'same_as'],
                "site.people.{$key}",
            );

            $url = $this->optionalString(
                $definition['url'] ?? null,
                "site.people.{$key}.url",
            );

            if ($url !== null) {
                $this->links->assertHttpOrPathUrl(
                    $url,
                    "site.people.{$key}.url",
                );
            }

            $sameAs = $definition['same_as'] ?? [];

            if (! is_array($sameAs) || ! array_is_list($sameAs)) {
                throw new InvalidArgumentException(
                    "Site configuration [site.people.{$key}.same_as] must be a list."
                );
            }

            $normalizedSameAs = [];

            foreach ($sameAs as $index => $sameAsUrl) {
                if (! is_string($sameAsUrl) || trim($sameAsUrl) === '') {
                    throw new InvalidArgumentException(
                        "Site configuration [site.people.{$key}.same_as.{$index}] must be a non-blank URL."
                    );
                }

                $sameAsUrl = trim($sameAsUrl);

                $this->links->assertUrl(
                    $sameAsUrl,
                    "site.people.{$key}.same_as.{$index}",
                    ['http', 'https'],
                    false,
                );

                $normalizedSameAs[] = $sameAsUrl;
            }

            $resolved[$key] = [
                'key' => $key,
                'name' => $this->requiredString(
                    $definition['name'] ?? null,
                    "site.people.{$key}.name",
                ),
                'url' => $url,
                'job_title' => $this->optionalString(
                    $definition['job_title'] ?? null,
                    "site.people.{$key}.job_title",
                ),
                'description' => $this->optionalString(
                    $definition['description'] ?? null,
                    "site.people.{$key}.description",
                ),
                'same_as' => array_values(array_unique($normalizedSameAs)),
            ];
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        $key = trim($key);

        if ($key === '') {
            return null;
        }

        return $this->all()[$key] ?? null;
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