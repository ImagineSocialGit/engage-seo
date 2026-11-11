<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\Credentials\SeoCredentialInstallationContract;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeFailure;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeProbe;
use App\Support\Deployment\Credentials\SeoInternalCredentialDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeoCredentialRuntimeFailureTest extends TestCase
{
    public function test_reader_failures_map_to_closed_diagnostics(): void
    {
        $cases = [
            'Selected SEO environment file is not private.' => 'selected_environment_permissions_invalid',
            'Invalid or duplicate selected SEO environment key.' => 'selected_environment_key_invalid',
            'Unsupported selected SEO environment syntax.' => 'selected_environment_syntax_invalid',
            'Unsafe selected SEO package configuration.' => 'selected_package_configuration_unsafe',
            'Unsafe selected SEO environment file.' => 'selected_environment_unsafe_file',
        ];
        foreach ($cases as $message => $finding) {
            self::assertSame($finding, SeoCredentialRuntimeFailure::finding(new RuntimeException($message), 'selected_environment'));
        }
    }

    public function test_unknown_messages_and_reasons_never_enter_diagnostics(): void
    {
        $secret = str_repeat('S', 64);
        foreach (['selected_environment', 'runtime_inventory', 'runtime_proof', 'target_selection'] as $phase) {
            $finding = SeoCredentialRuntimeFailure::finding(new RuntimeException($secret), $phase);
            self::assertStringNotContainsString($secret, $finding);
            self::assertMatchesRegularExpression('/\\A[a-z][a-z_]+\\z/', $finding);
            self::assertSame($finding, SeoCredentialRuntimeFailure::finding(new SeoCredentialRuntimeFailure($secret), $phase));
        }
    }

    public function test_inventory_reports_context_origin_and_ownership_separately(): void
    {
        $registry = new SeoCredentialInstallationRegistry();
        $registry->register(new SeoInternalCredentialDefinition(
            'fixture.checkout', 'fixture', 'core', true, 'fixture.core',
            'ENGAGE_SEO_FIXTURE_ID', 'ENGAGE_SEO_FIXTURE_SECRET', 'ENGAGE_SEO_FIXTURE_URL',
        ));
        $probe = new SeoCredentialRuntimeProbe($registry, new SeoCredentialInstallationContract($registry));
        $runtime = ['client_key' => 'fixture-seo', 'environment' => 'local', 'site_origin' => 'http://fixture.test',
            'client_environment_keys' => ['ENGAGE_SEO_FIXTURE_ID', 'ENGAGE_SEO_FIXTURE_SECRET', 'ENGAGE_SEO_FIXTURE_URL']];
        foreach ([
            ['client_key', '../other', 'runtime_context_invalid'],
            ['site_origin', 'http://fixture.test/path', 'runtime_origin_invalid'],
            ['client_environment_keys', [], 'runtime_binding_ownership_invalid'],
        ] as [$field, $value, $reason]) {
            try {
                $probe->inventory([...$runtime, $field => $value]);
                self::fail('Invalid inventory context must fail.');
            } catch (SeoCredentialRuntimeFailure $error) {
                self::assertSame($reason, SeoCredentialRuntimeFailure::finding($error, 'runtime_inventory'));
                self::assertSame('credential_runtime_inventory_failed', $error->getMessage());
            }
        }
    }
}