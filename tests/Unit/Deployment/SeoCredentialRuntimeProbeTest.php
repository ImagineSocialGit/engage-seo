<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\Credentials\SeoCredentialInstallationContract;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeProbe;
use App\Support\Deployment\Credentials\SeoInternalCredentialDefinition;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SeoCredentialRuntimeProbeTest extends TestCase
{
    private const ID = 'ENGAGE_SEO_FIXTURE_CLIENT_ID';
    private const SECRET = 'ENGAGE_SEO_FIXTURE_SIGNING_SECRET';
    private const ORIGIN = 'ENGAGE_SEO_FIXTURE_URL';

    public function test_inventory_discovers_active_package_bindings_without_credential_values(): void
    {
        $runtime = $this->runtime();
        unset($runtime['binding']);
        $report = $this->probe()->inventory($runtime);
        self::assertSame('fixture.checkout', $report['bindings'][0]['purpose']);
        self::assertSame('http://fixture-seo.test', $report['bindings'][0]['source_origin']);
        self::assertArrayNotHasKey('proof', $report);
        self::assertStringNotContainsString(str_repeat('A', 64), json_encode($report, JSON_THROW_ON_ERROR));
        self::assertFalse($report['writes_allowed']);
    }

    public function test_proof_uses_the_effective_package_binding_and_complete_protocol_context(): void
    {
        $nonce = str_repeat('b', 64);
        $secret = str_repeat('A', 64);
        $report = $this->prove($this->runtime(), $this->persisted(), $nonce);
        $canonical = implode("\n", ['engage-internal-runtime-v1', $nonce, 'seo', 'fixture-seo',
            'local', 'fixture.checkout', 'core', 'fixture_caller', 'http://fixture-seo.test', 'http://webhooks.fixture.test']);
        self::assertSame(hash_hmac('sha256', $canonical, $secret), $report['proof']);
        self::assertNotSame($report['proof'], $this->prove($this->runtime(), $this->persisted(), str_repeat('c', 64))['proof']);
        self::assertStringNotContainsString($secret, json_encode($report, JSON_THROW_ON_ERROR));
        self::assertFalse($report['credential_equality_verified']);
        self::assertFalse($report['network_verified']);
    }

    public function test_stale_cached_secret_cannot_prove_new_persisted_material(): void
    {
        $runtime = $this->runtime();
        $runtime['binding']['signing_secret'] = str_repeat('B', 64);
        $this->expectException(RuntimeException::class);
        $this->prove($runtime, $this->persisted());
    }

    public function test_missing_persisted_secret_blocks_even_if_effective_binding_is_present(): void
    {
        $persisted = $this->persisted();
        unset($persisted[self::SECRET]);
        $this->expectException(RuntimeException::class);
        $this->prove($this->runtime(), $persisted);
    }

    public function test_unregistered_purpose_never_falls_back_to_arbitrary_config(): void
    {
        $registry = new SeoCredentialInstallationRegistry();
        $probe = new SeoCredentialRuntimeProbe($registry, new SeoCredentialInstallationContract($registry));
        self::assertSame([], $probe->inventory($this->runtime())['bindings']);
        $this->expectException(RuntimeException::class);
        $probe->prove('fixture.checkout', 'fixture_caller', str_repeat('b', 64), $this->runtime(), $this->persisted());
    }

    public function test_inactive_definition_is_absent_from_inventory_and_cannot_prove(): void
    {
        $probe = $this->probe(false);
        self::assertSame([], $probe->inventory($this->runtime())['bindings']);
        $this->expectException(RuntimeException::class);
        $probe->prove('fixture.checkout', 'fixture_caller', str_repeat('b', 64), $this->runtime(), $this->persisted());
    }

    public function test_root_scope_cannot_substitute_for_any_selected_client_assignment(): void
    {
        foreach ($this->persisted() as $key => $value) {
            try {
                $this->probe()->prove('fixture.checkout', 'fixture_caller', str_repeat('b', 64),
                    $this->runtime(), $this->persisted(), [$key => $value]);
                self::fail('Root-scoped credential assignments must fail.');
            } catch (RuntimeException $exception) {
                self::assertSame('credential_runtime_verification_failed', $exception->getMessage());
            }
        }
    }

    public function test_wrong_caller_cannot_use_another_bindings_credentials(): void
    {
        $this->expectException(RuntimeException::class);
        $this->probe()->prove('fixture.checkout', 'different', str_repeat('b', 64), $this->runtime(), $this->persisted());
    }

    public function test_unowned_environment_key_blocks_inventory(): void
    {
        $runtime = $this->runtime();
        $runtime['client_environment_keys'] = [self::ID, self::ORIGIN];
        $this->expectException(RuntimeException::class);
        $this->probe()->inventory($runtime);
    }

    public function test_changed_effective_destination_cannot_match_persisted_destination(): void
    {
        $runtime = $this->runtime();
        $runtime['binding']['base_url'] = 'http://different.test';
        $this->expectException(RuntimeException::class);
        $this->prove($runtime, $this->persisted());
    }

    public function test_malformed_challenge_and_secret_return_only_fixed_errors(): void
    {
        $runtime = $this->runtime();
        $runtime['binding']['signing_secret'] = 'DO_NOT_EXPOSE';
        try {
            $this->prove($runtime, $this->persisted(), "bad\nnonce");
            self::fail('Malformed proof input must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('credential_runtime_verification_failed', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    public function test_environment_changes_require_corresponding_origin_policy(): void
    {
        $runtime = $this->runtime();
        $runtime['environment'] = 'production';
        $this->expectException(RuntimeException::class);
        $this->probe()->inventory($runtime);
    }

    public function test_registry_inventory_includes_every_active_purpose(): void
    {
        $registry = new SeoCredentialInstallationRegistry();
        $runtime = $this->runtime();
        foreach (['second', 'first'] as $name) {
            $prefix = 'ENGAGE_SEO_'.strtoupper($name);
            $registry->register(new SeoInternalCredentialDefinition(
                'fixture.'.$name, 'fixture', 'core', true, 'fixture.'.$name,
                $prefix.'_ID', $prefix.'_SECRET', $prefix.'_ORIGIN',
            ));
            array_push($runtime['client_environment_keys'], $prefix.'_ID', $prefix.'_SECRET', $prefix.'_ORIGIN');
        }
        $probe = new SeoCredentialRuntimeProbe($registry, new SeoCredentialInstallationContract($registry));
        $report = $probe->inventory($runtime);
        self::assertCount(2, $report['bindings']);
        self::assertEqualsCanonicalizing(['fixture.first', 'fixture.second'], array_column($report['bindings'], 'purpose'));
    }

    private function probe(bool $selected = true): SeoCredentialRuntimeProbe
    {
        $registry = new SeoCredentialInstallationRegistry();
        $registry->register(new SeoInternalCredentialDefinition(
            'fixture.checkout', 'fixture', 'core', $selected, 'fixture.checkout', self::ID, self::SECRET, self::ORIGIN,
        ));

        return new SeoCredentialRuntimeProbe($registry, new SeoCredentialInstallationContract($registry));
    }

    /** @return array<string, mixed> */
    private function runtime(): array
    {
        return [
            'client_key' => 'fixture-seo', 'environment' => 'local', 'site_origin' => 'http://fixture-seo.test',
            'client_environment_keys' => [self::ID, self::SECRET, self::ORIGIN],
            'binding' => ['client_id' => 'fixture_caller', 'signing_secret' => str_repeat('A', 64), 'base_url' => 'http://webhooks.fixture.test'],
        ];
    }

    /** @return array<string, string> */
    private function persisted(): array
    {
        return [self::ID => 'fixture_caller', self::SECRET => str_repeat('A', 64), self::ORIGIN => 'http://webhooks.fixture.test'];
    }

    /** @param array<string, mixed> $runtime @param array<string, string> $persisted @return array<string, mixed> */
    private function prove(array $runtime, array $persisted, string $nonce = ''): array
    {
        return $this->probe()->prove('fixture.checkout', 'fixture_caller',
            $nonce === '' ? str_repeat('b', 64) : $nonce, $runtime, $persisted);
    }
}