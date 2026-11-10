<?php

namespace App\Support\Deployment\Credentials;

use LogicException;

/** Package registrations are the authority for SEO runtime credential consumers. */
final class SeoCredentialInstallationRegistry
{
    /** @var array<string, SeoInternalCredentialDefinition> */
    private array $definitions = [];

    public function register(SeoInternalCredentialDefinition $definition): void
    {
        if (isset($this->definitions[$definition->purpose])) {
            throw new LogicException('Duplicate internal credential purpose registration.');
        }

        foreach ($this->definitions as $existing) {
            if (array_intersect(
                [$definition->identityKey, $definition->secretKey, $definition->originKey],
                [$existing->identityKey, $existing->secretKey, $existing->originKey],
            ) !== []) {
                throw new LogicException('Internal credential environment keys may not be shared.');
            }
        }

        $this->definitions[$definition->purpose] = $definition;
    }

    public function find(string $purpose): ?SeoInternalCredentialDefinition
    {
        return $this->definitions[$purpose] ?? null;
    }
}