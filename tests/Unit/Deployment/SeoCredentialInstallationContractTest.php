<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\Credentials\SeoCredentialInstallationContract;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use App\Support\Deployment\Credentials\SeoInternalCredentialDefinition;
use LogicException;
use PHPUnit\Framework\TestCase;

final class SeoCredentialInstallationContractTest extends TestCase
{
    private function registry(bool $selected = true): SeoCredentialInstallationRegistry
    {
        $registry = new SeoCredentialInstallationRegistry();
        $registry->register(new SeoInternalCredentialDefinition(
            'donations.external_checkout', 'nonprofit', 'core', $selected,
            'seo_nonprofit.core_donations', 'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID',
            'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET', 'ENGAGE_SEO_CORE_DONATIONS_URL',
        ));
        return $registry;
    }

    private function inspect(array $persisted = [], ?array $runtime = null, bool $selected = true): array
    {
        $registry = $this->registry($selected);
        return (new SeoCredentialInstallationContract($registry))->inspect(
            'donations.external_checkout', 'seo_donations', 'fixture-seo', 'local',
            'http://fixture-seo.test', 'http://webhooks.core.test',
            $runtime ?? [
                'client_key' => 'fixture-seo', 'environment' => 'local',
                'site_origin' => 'http://fixture-seo.test',
                'client_environment_keys' => [
                    'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID',
                    'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET',
                    'ENGAGE_SEO_CORE_DONATIONS_URL',
                ],
                'binding' => ['client_id' => null, 'signing_secret' => null, 'base_url' => null],
            ],
            $persisted,
        );
    }

    public function test_missing_package_owned_keys_are_pending_but_never_writable(): void
    {
        $report = $this->inspect();
        self::assertSame('pending_delivery', $report['status']);
        self::assertTrue($report['installation_target_recognized']);
        self::assertCount(3, $report['assignments']);
        self::assertFalse($report['delivery_allowed']);
        self::assertFalse($report['credential_equality_verified']);
    }

    public function test_present_credentials_are_unverified_not_new_install(): void
    {
        $key = str_repeat('A', 64);
        $persisted = [
            'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID' => 'seo_donations',
            'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET' => $key,
            'ENGAGE_SEO_CORE_DONATIONS_URL' => 'http://webhooks.core.test',
        ];
        $runtime = [
            'client_key' => 'fixture-seo', 'environment' => 'local',
            'site_origin' => 'http://fixture-seo.test',
            'client_environment_keys' => array_keys($persisted),
            'binding' => ['client_id' => 'seo_donations', 'signing_secret' => $key, 'base_url' => 'http://webhooks.core.test'],
        ];
        $report = $this->inspect($persisted, $runtime);
        self::assertSame('existing_secret_unverified', $report['status']);
        self::assertStringNotContainsString($key, json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_inactive_package_needs_no_keys(): void
    {
        self::assertSame('not_required', $this->inspect([], null, false)['status']);
    }

    public function test_missing_registration_does_not_infer_a_target(): void
    {
        $contract = new SeoCredentialInstallationContract(new SeoCredentialInstallationRegistry());
        $report = $contract->inspect('donations.external_checkout', 'seo_donations', 'fixture-seo', 'local',
            'http://fixture-seo.test', 'http://webhooks.core.test', [], []);
        self::assertSame('blocked', $report['status']);
    }

    public function test_unowned_keys_and_wrong_destination_block(): void
    {
        $runtime = [
            'client_key' => 'fixture-seo', 'environment' => 'local', 'site_origin' => 'http://fixture-seo.test',
            'client_environment_keys' => ['ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET'],
            'binding' => [],
        ];
        self::assertSame('blocked', $this->inspect([], $runtime)['status']);
    }

    public function test_effective_secret_with_no_persisted_value_is_blocked(): void
    {
        $runtime = [
            'client_key' => 'fixture-seo', 'environment' => 'local', 'site_origin' => 'http://fixture-seo.test',
            'client_environment_keys' => ['ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID', 'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET', 'ENGAGE_SEO_CORE_DONATIONS_URL'],
            'binding' => ['signing_secret' => str_repeat('B', 64)],
        ];
        self::assertSame('blocked', $this->inspect([], $runtime)['status']);
    }

    public function test_registry_rejects_duplicated_purpose_and_overlapping_keys(): void
    {
        $registry = $this->registry();
        $definition = $registry->find('donations.external_checkout');
        $this->expectException(LogicException::class);
        $registry->register($definition);
    }
}