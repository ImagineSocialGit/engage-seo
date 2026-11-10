<?php

namespace App\Support\Deployment\Credentials;

/** Generic SEO recipient installation evidence, not approval to write. */
final class SeoCredentialInstallationContract
{
    public function __construct(private readonly SeoCredentialInstallationRegistry $registry) {}

    /**
     * @param array<string, mixed> $runtime Selected SEO runtime identity and effective config
     * @param array<string, string> $persisted Selected client's PRIVATE .env snapshot
     * @return array<string, mixed> Redacted, read-only evidence
     */
    public function inspect(
        string $purpose,
        string $callerId,
        string $expectedClient,
        string $expectedEnvironment,
        string $expectedSourceOrigin,
        string $expectedDestinationOrigin,
        array $runtime,
        array $persisted,
    ): array {
        $definition = $this->registry->find($purpose);
        $findings = [];
        if ($definition === null) {
            $findings[] = 'owner_registration_missing';
        }
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/D', $callerId) !== 1) {
            $findings[] = 'caller_identity_invalid';
        }
        if (preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $expectedClient) !== 1
            || ! in_array($expectedEnvironment, ['local', 'staging', 'production'], true)
            || ($runtime['client_key'] ?? null) !== $expectedClient
            || ($runtime['environment'] ?? null) !== $expectedEnvironment
        ) {
            $findings[] = 'selected_client_identity_mismatch';
        }
        if (! $this->validOrigin($expectedSourceOrigin, $expectedEnvironment)
            || ! $this->validOrigin($expectedDestinationOrigin, $expectedEnvironment)
            || ($runtime['site_origin'] ?? null) !== $expectedSourceOrigin
        ) {
            $findings[] = 'installation_origin_invalid';
        }

        $selected = $definition?->selected ?? false;
        $assignments = [];
        if ($definition !== null && $selected) {
            $keys = $runtime['client_environment_keys'] ?? null;
            if (! is_array($keys) || ! array_is_list($keys) || count($keys) !== count(array_unique($keys))
                || count(array_intersect([$definition->identityKey, $definition->secretKey, $definition->originKey], $keys)) !== 3
            ) {
                $findings[] = 'package_environment_ownership_invalid';
            }
            $effective = $runtime['binding'] ?? null;
            if (! is_array($effective)) {
                $findings[] = 'owner_runtime_binding_missing';
            }
            foreach ([
                ['caller_identity', $definition->identityKey, $callerId, 'client_id'],
                ['destination_origin', $definition->originKey, $expectedDestinationOrigin, 'base_url'],
                ['signing_secret', $definition->secretKey, null, 'signing_secret'],
            ] as [$role, $key, $expected, $field]) {
                $present = array_key_exists($key, $persisted);
                $value = $persisted[$key] ?? null;
                $loaded = is_array($effective) ? ($effective[$field] ?? null) : null;
                if ($present) {
                    if (! is_string($value) || $value === '' || $loaded !== $value) {
                        $findings[] = 'recipient_persisted_runtime_mismatch';
                    } elseif ($role === 'signing_secret') {
                        if (preg_match('/\A[A-Za-z0-9_-]{64}\z/D', $value) !== 1) {
                            $findings[] = 'recipient_secret_invalid';
                        }
                    } elseif ($value !== $expected) {
                        $findings[] = 'recipient_existing_assignment_conflict';
                    }
                } elseif ($loaded !== null && $loaded !== '') {
                    $findings[] = 'unpersisted_runtime_credential';
                }
                $assignments[] = [
                    'role' => $role,
                    'key' => $key,
                    'state' => $present ? 'present_unverified' : 'missing',
                ];
            }
        }

        $findings = array_values(array_unique($findings));
        $status = match (true) {
            $findings !== [] => 'blocked',
            ! $selected => 'not_required',
            count(array_filter($assignments, static fn (array $a): bool => $a['state'] === 'missing')) > 0 => 'pending_delivery',
            default => 'existing_secret_unverified',
        };

        return [
            'schema' => 1,
            'platform' => 'seo',
            'client_key' => $expectedClient,
            'environment' => $expectedEnvironment,
            'purpose' => $definition?->purpose,
            'destination_platform' => $definition?->destinationPlatform,
            'caller_id' => $callerId,
            'status' => $status,
            'capability_selected' => $selected,
            'owner' => $definition?->owner,
            'installation_target_recognized' => $selected && $findings === [],
            'assignments' => $status === 'blocked' ? [] : $assignments,
            'findings' => $findings,
            'runtime_binding_verified' => false,
            'credential_equality_verified' => false,
            'delivery_allowed' => false,
            'writes_allowed' => false,
        ];
    }

    private function validOrigin(string $value, string $environment): bool
    {
        $parts = parse_url($value);
        if (! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, $environment === 'local' ? ['http', 'https'] : ['https'], true)
            || ! is_string($parts['host'] ?? null) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== '' || isset($parts['port'])
            || str_ends_with($value, '/') || str_contains($value, '\\')
        ) {
            return false;
        }
        return filter_var($parts['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            || filter_var($parts['host'], FILTER_VALIDATE_IP) !== false;
    }
}