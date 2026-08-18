<?php

namespace App\Support\Features;

use App\Support\Verticals\VerticalManager;
use Illuminate\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

class FeatureManager
{
    public function __construct(
        protected Application $app,
        protected VerticalManager $verticals,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function available(): array
    {
        $available = config('features.available', []);

        return is_array($available) ? $available : [];
    }

    /**
     * @return list<string>
     */
    public function enabledKeys(): array
    {
        $enabled = array_merge(
            $this->verticals->defaultFeatures(),
            $this->stringList(config('features.enabled', [])),
        );

        $enabled = array_values(array_unique($enabled));

        $disabled = $this->stringList(config('features.disabled', []));

        return array_values(array_diff($enabled, $disabled));
    }

    public function enabled(string $featureKey): bool
    {
        return in_array($featureKey, $this->enabledKeys(), true);
    }

    /**
     * @return list<class-string>
     */
    public function providerClasses(): array
    {
        $providers = [];

        foreach ($this->enabledKeys() as $featureKey) {
            $definition = $this->available()[$featureKey] ?? [];
            $provider = $definition['provider'] ?? null;

            if ($provider === null) {
                continue;
            }

            if (! is_string($provider) || ! class_exists($provider)) {
                throw new RuntimeException(
                    "Feature '{$featureKey}' has an invalid service provider."
                );
            }

            $providers[] = $provider;
        }

        return array_values(array_unique($providers));
    }

    public function registerProviders(): void
    {
        foreach ($this->providerClasses() as $provider) {
            $this->app->register($provider);
        }
    }

    public function assertValid(): void
    {
        $this->verticals->assertValid();

        $availableKeys = array_keys($this->available());

        $configuredKeys = array_values(array_unique(array_merge(
            $this->verticals->defaultFeatures(),
            $this->stringList(config('features.enabled', [])),
            $this->stringList(config('features.disabled', [])),
        )));

        $unknown = array_values(array_diff(
            $configuredKeys,
            $availableKeys
        ));

        if ($unknown !== []) {
            throw new InvalidArgumentException(
                'Unknown Engage SEO feature(s): '.implode(', ', $unknown)
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = array_map(
            fn (string $item): string => trim($item),
            array_filter(
                $value,
                fn (mixed $item): bool => is_string($item)
                    && trim($item) !== ''
            )
        );

        return array_values(array_unique($items));
    }
}