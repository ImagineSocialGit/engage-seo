<?php

namespace App\Support\Verticals;

use InvalidArgumentException;

class VerticalManager
{
    public function selectedKey(): ?string
    {
        $selected = config('client.vertical');

        if (! is_string($selected) || trim($selected) === '') {
            return null;
        }

        return trim($selected);
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
    public function defaultFeatures(): array
    {
        $features = $this->selected()['default_features'] ?? [];

        if (! is_array($features)) {
            return [];
        }

        $features = array_map(
            fn (string $feature): string => trim($feature),
            array_filter(
                $features,
                fn (mixed $feature): bool => is_string($feature)
                    && trim($feature) !== ''
            )
        );

        return array_values(array_unique($features));
    }

    public function assertValid(): void
    {
        $key = $this->selectedKey();

        if ($key === null) {
            return;
        }

        if (! array_key_exists($key, $this->available())) {
            throw new InvalidArgumentException(
                "Unknown Engage SEO vertical: {$key}"
            );
        }
    }
}