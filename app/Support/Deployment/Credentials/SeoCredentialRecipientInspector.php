<?php

namespace App\Support\Deployment\Credentials;

/**
 * Read-only evidence for an SEO selected-client signing-credential recipient.
 *
 * This inspects effective configuration. It does not inspect credential values
 * for equality with Core, authorize new callers, issue credentials or write env.
 */
final class SeoCredentialRecipientInspector
{
    private const ROUTES = [
        'forms.external_intake' => [
            'identity_key' => 'ENGAGE_SEO_CORE_FORMS_CLIENT_ID',
            'secret_key' => 'ENGAGE_SEO_CORE_FORMS_SIGNING_SECRET',
            'origin_key' => 'ENGAGE_SEO_CORE_FORMS_URL',
            'config_key' => 'core_forms',
        ],
        'donations.external_checkout' => [
            'identity_key' => 'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID',
            'secret_key' => 'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET',
            'origin_key' => 'ENGAGE_SEO_CORE_DONATIONS_URL',
            'config_key' => 'core_donations',
        ],
    ];

    /**
     * @param array<string, mixed> $runtime Evidence assembled from the already
     *                                      selected SEO application runtime.
     * @return array<string, mixed> Redacted inspection, never an authorization.
     */
    public function inspect(
        string $purpose,
        string $callerId,
        string $expectedClient,
        string $expectedEnvironment,
        string $expectedSourceOrigin,
        string $expectedCoreOrigin,
        array $runtime,
    ): array {
        $findings = [];
        $route = self::ROUTES[$purpose] ?? null;
        $validClient = $this->validClient($expectedClient);
        $validCaller = $this->validCaller($callerId);
        $validEnvironment = in_array($expectedEnvironment, ['local', 'staging', 'production'], true);

        if ($route === null) {
            $findings[] = 'unsupported_pairing_route';
        }
        if (! $validCaller) {
            $findings[] = 'invalid_caller_identity';
        }
        if (! $validClient || ! $validEnvironment
            || ($runtime['client_key'] ?? null) !== $expectedClient
            || ($runtime['environment'] ?? null) !== $expectedEnvironment
        ) {
            $findings[] = 'target_identity_mismatch';
        }

        $sourceOriginValid = $this->validOrigin($expectedSourceOrigin, $expectedEnvironment);
        $coreOriginValid = $this->validOrigin($expectedCoreOrigin, $expectedEnvironment);
        if (! $sourceOriginValid || ! $coreOriginValid) {
            $findings[] = 'invalid_pair_origin';
        }
        $sourceOriginMatch = $sourceOriginValid
            && ($runtime['site_origin'] ?? null) === $expectedSourceOrigin;
        if (! $sourceOriginMatch) {
            $findings[] = 'source_origin_mismatch';
        }

        $selected = false;
        $identityPresent = false;
        $secretPresent = false;
        $coreOriginMatch = null;

        if ($route !== null) {
            $features = $runtime['enabled_features'] ?? null;
            $bindings = $runtime['form_bindings'] ?? null;
            $donationsEnabled = $runtime['donations_enabled'] ?? null;
            if (! is_array($features) || ! array_is_list($features)
                || count(array_filter($features, 'is_string')) !== count($features)
                || ! is_array($bindings) || ! is_bool($donationsEnabled)
            ) {
                $findings[] = 'capability_evidence_invalid';
            } else {
                $formsEnabled = in_array('forms', $features, true);
                $activeForms = false;
                foreach ($bindings as $key => $binding) {
                    if (! is_string($key) || ! is_array($binding)
                        || ! is_bool($binding['enabled'] ?? false)
                    ) {
                        $findings[] = 'form_binding_evidence_invalid';
                        continue;
                    }
                    if ($binding['enabled'] === true) {
                        if (preg_match('/\A[a-z][a-z0-9_]*\z/D', $key) !== 1) {
                            $findings[] = 'form_binding_evidence_invalid';
                        }
                        $activeForms = true;
                    }
                }
                if ($activeForms && ! $formsEnabled) {
                    $findings[] = 'forms_selection_conflict';
                }
                if ($donationsEnabled && ! $formsEnabled) {
                    $findings[] = 'donations_requires_forms';
                }

                if ($purpose === 'forms.external_intake') {
                    $selected = $activeForms && $formsEnabled;
                } else {
                    $selected = $donationsEnabled;
                    if ($selected && ($runtime['nonprofit_provider_selected'] ?? null) !== true) {
                        $findings[] = 'donations_provider_not_selected';
                    }
                }
            }

            if ($selected) {
                $declared = $runtime['client_environment_keys'] ?? null;
                if (! is_array($declared) || ! array_is_list($declared)
                    || count(array_filter($declared, 'is_string')) !== count($declared)
                ) {
                    $findings[] = 'recipient_environment_catalog_invalid';
                } else {
                    foreach ([$route['identity_key'], $route['secret_key'], $route['origin_key']] as $key) {
                        if (! in_array($key, $declared, true)) {
                            $findings[] = 'recipient_environment_key_not_declared';
                            break;
                        }
                    }
                }

                $pair = $runtime[$route['config_key']] ?? null;
                if (! is_array($pair)) {
                    $findings[] = 'recipient_configuration_invalid';
                } else {
                    $configuredId = $pair['client_id'] ?? null;
                    if (is_string($configuredId) && $configuredId !== '') {
                        if (! $this->validCaller($configuredId) || $configuredId !== $callerId) {
                            $findings[] = 'recipient_caller_identity_mismatch';
                        } else {
                            $identityPresent = true;
                        }
                    } elseif ($configuredId !== null && $configuredId !== '') {
                        $findings[] = 'recipient_caller_identity_invalid';
                    }

                    $secret = $pair['signing_secret'] ?? null;
                    if ($secret !== null && $secret !== '') {
                        if (! is_string($secret) || strlen($secret) < 32 || strlen($secret) > 4096) {
                            $findings[] = 'recipient_secret_invalid';
                        } else {
                            $secretPresent = true;
                        }
                    }

                    $configuredCoreOrigin = $pair['base_url'] ?? null;
                    if ($configuredCoreOrigin === null || $configuredCoreOrigin === '') {
                        $coreOriginMatch = false;
                    } else {
                        $coreOriginMatch = $coreOriginValid
                            && $this->validOrigin($configuredCoreOrigin, $expectedEnvironment)
                            && $configuredCoreOrigin === $expectedCoreOrigin;
                        if (! $coreOriginMatch) {
                            $findings[] = 'recipient_core_origin_mismatch';
                        }
                    }
                }
            }
        }

        $findings = array_values(array_unique($findings));
        $status = match (true) {
            $findings !== [] => 'blocked',
            ! $selected => 'not_required',
            ! $identityPresent || ! $secretPresent || $coreOriginMatch !== true => 'needs_provisioning',
            default => 'configured_unverified',
        };

        return [
            'schema' => 1,
            'platform' => 'seo',
            'client_key' => $validClient ? $expectedClient : null,
            'environment' => $validEnvironment ? $expectedEnvironment : null,
            'purpose' => $route !== null ? $purpose : null,
            'destination_platform' => 'core',
            'caller_id' => $validCaller ? $callerId : null,
            'status' => $status,
            'capability_selected' => $selected,
            'recipient_identity_present' => $identityPresent,
            'recipient_secret_present' => $secretPresent,
            'source_origin_match' => $sourceOriginMatch,
            'core_origin_match' => $coreOriginMatch,
            'findings' => $findings,
            'peer_verified' => false,
            'credential_match_verified' => false,
            'authorization_verified' => false,
            'issuance_allowed' => false,
            'writes_allowed' => false,
        ];
    }

    private function validClient(string $value): bool
    {
        return preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $value) === 1;
    }

    private function validCaller(string $value): bool
    {
        return preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/D', $value) === 1;
    }

    private function validOrigin(mixed $value, string $environment): bool
    {
        if (! is_string($value) || $value === '' || trim($value) !== $value
            || str_ends_with($value, '/')) {
            return false;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || ! in_array($parts['scheme'] ?? null, $environment === 'local' ? ['http', 'https'] : ['https'], true)
            || ! is_string($parts['host'] ?? null) || $parts['host'] === ''
            || ! $this->validHost($parts['host'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            return false;
        }

        foreach (['user', 'pass', 'query', 'fragment'] as $part) {
            if (array_key_exists($part, $parts)) {
                return false;
            }
        }

        return true;
    }

    private function validHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
            || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
    }
}