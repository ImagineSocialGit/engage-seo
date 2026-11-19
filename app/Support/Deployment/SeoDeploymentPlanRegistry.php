<?php

namespace App\Support\Deployment;

use InvalidArgumentException;
use LogicException;

/**
 * Package/feature-owned, read-only deployment declarations.
 *
 * Registration does not imply installed credentials, routes, or readiness.
 * Every selected external provider must explicitly register, even when its
 * contribution is empty because the client has disabled its functionality.
 */
final class SeoDeploymentPlanRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $contributions = [];

    /**
     * @param list<array{key:string, secret:bool}> $environmentKeys
     * @param list<string> $setupSteps
     * @param list<string> $capabilities
     * @param list<string> $operations
     */
    public function register(
        string $provider,
        string $owner,
        array $environmentKeys = [],
        array $setupSteps = [],
        array $capabilities = [],
        array $operations = [],
    ): void {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D', $provider) !== 1
            || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $owner) !== 1
            || isset($this->contributions[$provider])) {
            throw new InvalidArgumentException('Invalid or duplicate SEO deployment contributor.');
        }
        if (count($environmentKeys) > 64 || count($setupSteps) > 64 || count($capabilities) > 64 || count($operations) > 64
            || ! array_is_list($environmentKeys) || ! array_is_list($setupSteps)
            || ! array_is_list($capabilities) || ! array_is_list($operations)) {
            throw new InvalidArgumentException('Invalid SEO deployment contribution shape.');
        }
        $keys = [];
        foreach ($environmentKeys as $item) {
            if (! is_array($item) || count($item) !== 2
                || ! isset($item['key']) || ! array_key_exists('secret', $item)
                || ! is_string($item['key']) || ! is_bool($item['secret'])
                || preg_match('/\A[A-Z][A-Z0-9_]*\z/D', $item['key']) !== 1
                || isset($keys[$item['key']])) {
                throw new InvalidArgumentException('Invalid SEO environment key declaration.');
            }
            $keys[$item['key']] = ['key' => $item['key'], 'owner' => $owner,
                'scope' => 'client', 'secret' => $item['secret'],
                'requirement' => 'required', 'status' => 'unverified'];
        }
        foreach ([$setupSteps, $capabilities, $operations] as $items) {
            if (count(array_filter($items, 'is_string')) !== count($items)) {
                throw new InvalidArgumentException('Non-string SEO deployment declaration.');
            }
            if (count(array_unique($items)) !== count($items)) {
                throw new InvalidArgumentException('Duplicate SEO deployment declaration.');
            }
            foreach ($items as $item) {
                if (! is_string($item)
                    || preg_match('/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+\z/D', $item) !== 1) {
                    throw new InvalidArgumentException('Invalid SEO deployment declaration identifier.');
                }
            }
        }
        $this->contributions[$provider] = [
            'owner' => $owner,
            'environment_requirements' => array_values($keys),
            'setup_steps' => array_map(static fn (string $step): array => [
                'owner' => $owner, 'key' => $step,
                'environment_keys' => array_keys($keys),
            ], $setupSteps),
            'capabilities' => array_map(static fn (string $key): array => [
                'owner' => $owner, 'key' => $key,
            ], $capabilities),
            'operations' => array_map(static fn (string $key): array => [
                'owner' => $owner, 'key' => $key,
            ], $operations),
        ];
    }

    /** @param list<string> $providers @param list<string> $features */
    public function inspect(array $providers, array $features): array
    {
        if (! array_is_list($providers) || ! array_is_list($features)
            || count($providers) > 128 || count($features) > 128
            || count(array_filter($providers, 'is_string')) !== count($providers)
            || count(array_filter($features, 'is_string')) !== count($features)) {
            throw new InvalidArgumentException('Invalid selected SEO provider inventory.');
        }
        $required = array_values(array_unique($providers));
        sort($required, SORT_STRING);
        $missing = array_values(array_diff($required, array_keys($this->contributions)));
        sort($missing, SORT_STRING);
        $owners = ['seo'];
        $requirements = [];
        $steps = [];
        $capabilities = [];
        $operations = [];
        $ownedKeys = [];
        foreach ($this->contributions as $provider => $contribution) {
            if (! in_array($provider, $required, true)) {
                continue;
            }
            $owners[] = $contribution['owner'];
            foreach ($contribution['environment_requirements'] as $item) {
                if (isset($ownedKeys[$item['key']])) {
                    throw new LogicException('Multiple SEO deployment owners declared the same environment key.');
                }
                $ownedKeys[$item['key']] = true;
                $requirements[] = $item;
            }
            array_push($steps, ...$contribution['setup_steps']);
            array_push($capabilities, ...$contribution['capabilities']);
            array_push($operations, ...$contribution['operations']);
        }
        if (count(array_unique($owners)) !== count($owners)) {
            throw new LogicException('Duplicate SEO deployment owner.');
        }
        sort($owners, SORT_STRING);
        sort($features, SORT_STRING);
        usort($requirements, static fn ($a, $b) => strcmp($a['key'], $b['key']));
        usort($steps, static fn ($a, $b) => strcmp($a['key'], $b['key']));
        usort($capabilities, static fn ($a, $b) => strcmp($a['key'], $b['key']));
        usort($operations, static fn ($a, $b) => strcmp($a['key'], $b['key']));
        foreach ([$steps, $capabilities, $operations] as $items) {
            $ids = array_column($items, 'key');
            if (count(array_unique($ids)) !== count($ids)) {
                throw new LogicException('Duplicate SEO deployment operation identity.');
            }
        }
        return [
            'schema' => 1, 'platform' => 'seo',
            'covered_owners' => $owners, 'enabled_features' => $features,
            'required_provider_count' => count($required),
            'covered_provider_count' => count($required) - count($missing),
            'missing_provider_contributors' => $missing,
            'environment_requirements' => $requirements,
            'setup_steps' => $steps,
            'public_capabilities' => $capabilities,
            'operations' => $operations,
            'declared_complete' => $missing === [],
            'runtime_verified' => false, 'writes_allowed' => false,
        ];
    }
}
