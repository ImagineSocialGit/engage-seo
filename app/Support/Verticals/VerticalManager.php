<?php

namespace App\Support\Verticals;

use InvalidArgumentException;

class VerticalManager
{
    private const DEFINITION_KEYS = [
        'name',
        'default_features',
    ];

    public function selectedKey(): ?string
    {
        $selected = config('client.vertical');

        if ($selected === null) {
            return null;
        }

        if (! is_string($selected)) {
            return null;
        }

        $selected = trim($selected);

        return $selected !== ''
            ? $selected
            : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function available(): array
    {
        $available = config('verticals.available', []);

        return is_array($available) ? $available : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function selected(): ?array
    {
        $key = $this->selectedKey();

        if ($key === null) {
            return null;
        }

        $definition = $this->available()[$key] ?? null;

        return is_array($definition) ? $definition : null;
    }

    /**
     * @return list<string>
     */
    public function defaultFeatures(?string $verticalKey = null): array
    {
        $verticalKey ??= $this->selectedKey();

        if ($verticalKey === null) {
            return [];
        }

        $definition = $this->available()[$verticalKey] ?? null;

        if (! is_array($definition)) {
            return [];
        }

        $features = $definition['default_features'] ?? [];

        if (! is_array($features)) {
            return [];
        }

        $features = array_map(
            static fn (string $feature): string => trim($feature),
            array_filter(
                $features,
                static fn (mixed $feature): bool => is_string($feature)
                    && trim($feature) !== '',
            ),
        );

        return array_values(array_unique($features));
    }

    public function assertValid(): void
    {
        $rawAvailable = config('verticals.available', []);

        if (! is_array($rawAvailable)) {
            throw new InvalidArgumentException(
                'Engage SEO [verticals.available] must be an array.'
            );
        }

        foreach ($rawAvailable as $key => $definition) {
            if (! is_string($key)
                || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Engage SEO vertical keys must use lowercase letters, numbers, hyphens, and underscores.'
                );
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException(
                    "Engage SEO vertical [{$key}] must be an array."
                );
            }

            $unknownKeys = array_values(array_diff(
                array_map(
                    static fn (mixed $definitionKey): string => (string) $definitionKey,
                    array_keys($definition),
                ),
                self::DEFINITION_KEYS,
            ));

            sort($unknownKeys);

            if ($unknownKeys !== []) {
                throw new InvalidArgumentException(
                    "Engage SEO vertical [{$key}] contains unsupported key(s): "
                    .implode(', ', $unknownKeys).'.'
                );
            }

            $name = $definition['name'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException(
                    "Engage SEO vertical [{$key}.name] must be a non-blank string."
                );
            }

            $features = $definition['default_features'] ?? [];

            if (! is_array($features) || ! array_is_list($features)) {
                throw new InvalidArgumentException(
                    "Engage SEO vertical [{$key}.default_features] must be a list."
                );
            }

            $seen = [];

            foreach ($features as $index => $feature) {
                if (! is_string($feature)
                    || preg_match('/^[a-z0-9][a-z0-9_-]*$/', trim($feature)) !== 1
                ) {
                    throw new InvalidArgumentException(
                        "Engage SEO vertical [{$key}.default_features.{$index}] must be a valid Feature key."
                    );
                }

                $feature = trim($feature);

                if (array_key_exists($feature, $seen)) {
                    throw new InvalidArgumentException(
                        "Engage SEO vertical [{$key}.default_features] contains duplicate Feature [{$feature}]."
                    );
                }

                $seen[$feature] = true;
            }
        }

        $selected = config('client.vertical');

        if ($selected !== null && ! is_string($selected)) {
            throw new InvalidArgumentException(
                'Configured Engage SEO vertical must be null or a string.'
            );
        }

        $key = $this->selectedKey();

        if ($key === null) {
            return;
        }

        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1) {
            throw new InvalidArgumentException(
                "Configured Engage SEO vertical key is invalid: {$key}"
            );
        }

        if (! array_key_exists($key, $rawAvailable)) {
            throw new InvalidArgumentException(
                "Unknown Engage SEO vertical: {$key}"
            );
        }
    }
}