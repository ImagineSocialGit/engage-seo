<?php

namespace App\Support\Editorial;

use App\Contracts\Editorial\EditorialPromotionContributor;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class EditorialPromotionRegistry
{
    /**
     * @var list<class-string<EditorialPromotionContributor>>
     */
    private array $contributors = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    /**
     * @param class-string<EditorialPromotionContributor> $contributor
     */
    public function register(string $contributor): void
    {
        if (! class_exists($contributor)
            || ! is_subclass_of($contributor, EditorialPromotionContributor::class)
        ) {
            throw new InvalidArgumentException(
                'Editorial promotion contributor must implement '
                .EditorialPromotionContributor::class
                .": {$contributor}"
            );
        }

        if (! in_array($contributor, $this->contributors, true)) {
            $this->contributors[] = $contributor;
        }
    }

    /**
     * @return array<string, EditorialPromotionContributor>
     */
    public function keyedContributors(): array
    {
        $resolved = [];

        foreach ($this->contributors as $contributorClass) {
            $contributor = $this->container->make($contributorClass);
            $key = trim($contributor->key());

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key) !== 1) {
                throw new InvalidArgumentException(
                    "Editorial promotion contributor [{$contributorClass}] returned invalid key [{$key}]."
                );
            }

            if (array_key_exists($key, $resolved)) {
                throw new InvalidArgumentException(
                    "Duplicate editorial promotion contributor key [{$key}]."
                );
            }

            $resolved[$key] = $contributor;
        }

        ksort($resolved);

        return $resolved;
    }
}