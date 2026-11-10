<?php

namespace App\Support\Deployment\Credentials;

use InvalidArgumentException;

/** Package-owned declaration of an internal connection consumed by SEO. */
final readonly class SeoInternalCredentialDefinition
{
    public function __construct(
        public string $purpose,
        public string $owner,
        public string $destinationPlatform,
        public bool $selected,
        public string $configurationPath,
        public string $identityKey,
        public string $secretKey,
        public string $originKey,
    ) {
        if (preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/D', $purpose) !== 1
            || preg_match('/\A[a-z][a-z0-9_]*\z/D', $owner) !== 1
            || preg_match('/\A[a-z][a-z0-9_]*\z/D', $destinationPlatform) !== 1
            || preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/D', $configurationPath) !== 1
            || count(array_unique([$identityKey, $secretKey, $originKey])) !== 3
        ) {
            throw new InvalidArgumentException('Invalid internal credential declaration.');
        }

        foreach ([$identityKey, $secretKey, $originKey] as $key) {
            if (preg_match('/\AENGAGE_SEO_[A-Z0-9_]+\z/D', $key) !== 1) {
                throw new InvalidArgumentException('Internal recipient key must be selected-client owned.');
            }
        }
    }
}