<?php

namespace App\Support\Deployment\Credentials;

use RuntimeException;
use Throwable;

/** Prove the package-declared effective binding; never return credential values. */
final class SeoCredentialRuntimeProbe
{
    public function __construct(
        private readonly SeoCredentialInstallationRegistry $registry,
        private readonly SeoCredentialInstallationContract $installation,
    ) {}

    /** @param array<string, mixed> $runtime @return array<string, mixed> */
    public function inventory(array $runtime): array
    {
        try {
            $this->validateIdentity($runtime);
            $bindings = [];
            foreach ($this->registry->all() as $definition) {
                if (! $definition->selected) {
                    continue;
                }
                $this->validateOwnership($definition, $runtime);
                $bindings[] = [
                    'purpose' => $definition->purpose,
                    'owner' => $definition->owner,
                    'destination_platform' => $definition->destinationPlatform,
                    'source_origin' => $runtime['site_origin'],
                    'identity_environment_key' => $definition->identityKey,
                    'secret_environment_key' => $definition->secretKey,
                    'origin_environment_key' => $definition->originKey,
                ];
            }

            return [
                ...$this->envelope($runtime),
                'status' => 'runtime_bindings_declared',
                'bindings' => $bindings,
            ];
        } catch (Throwable) {
            throw new RuntimeException('credential_runtime_inventory_failed');
        }
    }

    /**
     * @param array<string, mixed> $runtime Includes the definition's effective binding
     * @param array<string, string> $persisted Selected-client dotenv values
     * @param array<string, mixed> $rootEnvironment Root dotenv values for scope checks
     * @return array<string, mixed>
     */
    public function prove(
        string $purpose,
        string $callerId,
        string $challenge,
        array $runtime,
        array $persisted,
        array $rootEnvironment = [],
    ): array {
        try {
            $this->validateIdentity($runtime);
            $definition = $this->registry->find($purpose);
            if ($definition === null || ! $definition->selected
                || preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/D', $callerId) !== 1
                || preg_match('/\A[0-9a-f]{64}\z/D', $challenge) !== 1
            ) {
                throw new RuntimeException();
            }
            $this->validateOwnership($definition, $runtime);
            foreach ([$definition->identityKey, $definition->secretKey, $definition->originKey] as $key) {
                if (array_key_exists($key, $rootEnvironment)) {
                    throw new RuntimeException();
                }
            }
            $binding = $runtime['binding'] ?? null;
            $destination = is_array($binding) ? ($binding['base_url'] ?? null) : null;
            if (! is_string($destination) || ! $this->validOrigin($destination, $runtime['environment'])) {
                throw new RuntimeException();
            }
            // The existing owner contract compares EVERY effective field with
            // its selected-client persisted assignment, including cached config.
            $report = $this->installation->inspect(
                purpose: $purpose,
                callerId: $callerId,
                expectedClient: $runtime['client_key'],
                expectedEnvironment: $runtime['environment'],
                expectedSourceOrigin: $runtime['site_origin'],
                expectedDestinationOrigin: $destination,
                runtime: $runtime,
                persisted: $persisted,
            );
            if ($report['status'] !== 'existing_secret_unverified'
                || $report['installation_target_recognized'] !== true
            ) {
                throw new RuntimeException();
            }
            $secret = $binding['signing_secret'] ?? null;
            if (! is_string($secret) || preg_match('/\A[A-Za-z0-9_-]{64}\z/D', $secret) !== 1) {
                throw new RuntimeException();
            }
            $canonical = implode("\n", [
                'engage-internal-runtime-v1', $challenge, 'seo',
                $runtime['client_key'], $runtime['environment'], $purpose,
                $definition->destinationPlatform, $callerId, $runtime['site_origin'], $destination,
            ]);

            return [
                ...$this->envelope($runtime),
                'status' => 'runtime_binding_proven',
                'purpose' => $purpose,
                'peer_platform' => $definition->destinationPlatform,
                'caller_id' => $callerId,
                'source_origin' => $runtime['site_origin'],
                'destination_origin' => $destination,
                'challenge' => $challenge,
                'proof' => hash_hmac('sha256', $canonical, $secret),
            ];
        } catch (Throwable) {
            throw new RuntimeException('credential_runtime_verification_failed');
        }
    }

    /** @param array<string, mixed> $runtime */
    private function validateIdentity(array $runtime): void
    {
        $client = $runtime['client_key'] ?? null;
        $environment = $runtime['environment'] ?? null;
        $origin = $runtime['site_origin'] ?? null;
        if (! is_string($client) || preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $client) !== 1
            || ! in_array($environment, ['local', 'staging', 'production'], true)
            || ! is_string($origin) || ! $this->validOrigin($origin, $environment)
        ) {
            throw new RuntimeException();
        }
    }

    /** @param array<string, mixed> $runtime */
    private function validateOwnership(SeoInternalCredentialDefinition $definition, array $runtime): void
    {
        $keys = $runtime['client_environment_keys'] ?? null;
        if (! is_array($keys) || ! array_is_list($keys)
            || count(array_filter($keys, 'is_string')) !== count($keys)
            || count(array_unique($keys)) !== count($keys)
            || count(array_intersect([$definition->identityKey, $definition->secretKey, $definition->originKey], $keys)) !== 3
        ) {
            throw new RuntimeException();
        }
    }

    /** @param array<string, mixed> $runtime @return array<string, mixed> */
    private function envelope(array $runtime): array
    {
        return [
            'schema' => 1, 'platform' => 'seo',
            'client_key' => $runtime['client_key'], 'environment' => $runtime['environment'],
            'proof_protocol' => 'engage-internal-runtime-v1',
            'credential_equality_verified' => false,
            'network_verified' => false, 'writes_allowed' => false,
        ];
    }

    private function validOrigin(string $origin, string $environment): bool
    {
        $parts = parse_url($origin);

        return is_array($parts)
            && in_array($parts['scheme'] ?? null, $environment === 'local' ? ['http', 'https'] : ['https'], true)
            && is_string($parts['host'] ?? null) && $parts['host'] !== ''
            && filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            && array_intersect(['user', 'pass', 'port', 'path', 'query', 'fragment'], array_keys($parts)) === []
            && ! str_contains($origin, '\\') && trim($origin) === $origin;
    }
}