<?php

namespace Tests\Unit\Deployment;

use App\Support\Deployment\Credentials\SeoCredentialRecipientInspector;
use PHPUnit\Framework\TestCase;

final class SeoCredentialRecipientInspectorTest extends TestCase
{
    private SeoCredentialRecipientInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new SeoCredentialRecipientInspector();
    }

    public function test_installed_forms_feature_without_public_binding_does_not_require_pair(): void
    {
        $runtime = $this->runtime();
        $runtime['form_bindings'] = [];

        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('not_required', $result['status']);
        $this->assertFalse($result['capability_selected']);
        $this->assertFalse($result['issuance_allowed']);
    }

    public function test_selected_forms_with_no_credential_needs_provisioning(): void
    {
        $result = $this->inspect('forms.external_intake');
        $this->assertSame('needs_provisioning', $result['status']);
        $this->assertTrue($result['capability_selected']);
        $this->assertFalse($result['recipient_secret_present']);
    }

    public function test_valid_forms_configuration_remains_unverified(): void
    {
        $runtime = $this->runtime();
        $runtime['core_forms'] = [
            'client_id' => 'seo_forms',
            'signing_secret' => str_repeat('F', 48),
            'base_url' => 'http://webhooks.core.test',
        ];

        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('configured_unverified', $result['status']);
        $this->assertFalse($result['peer_verified']);
        $this->assertFalse($result['credential_match_verified']);
        $this->assertFalse($result['authorization_verified']);
        $this->assertFalse($result['writes_allowed']);
    }

    public function test_donations_requires_own_selected_capability_not_just_installed_package(): void
    {
        $runtime = $this->runtime();
        $runtime['donations_enabled'] = false;
        $this->assertSame('not_required', $this->inspect('donations.external_checkout', $runtime)['status']);
    }

    public function test_donations_without_credentials_needs_provisioning(): void
    {
        $result = $this->inspect('donations.external_checkout');
        $this->assertSame('needs_provisioning', $result['status']);
        $this->assertTrue($result['capability_selected']);
    }

    public function test_donations_with_valid_configuration_are_not_authorized_or_matched(): void
    {
        $runtime = $this->runtime();
        $runtime['core_donations'] = [
            'client_id' => 'seo_forms',
            'signing_secret' => str_repeat('S', 40),
            'base_url' => 'http://webhooks.core.test',
        ];
        $result = $this->inspect('donations.external_checkout', $runtime);
        $this->assertSame('configured_unverified', $result['status']);
        $this->assertFalse($result['issuance_allowed']);
        $this->assertFalse($result['credential_match_verified']);
    }

    public function test_donations_provider_must_be_selected(): void
    {
        $runtime = $this->runtime();
        $runtime['nonprofit_provider_selected'] = false;
        $result = $this->inspect('donations.external_checkout', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('donations_provider_not_selected', $result['findings']);
    }

    public function test_public_form_binding_without_feature_is_a_selection_error(): void
    {
        $runtime = $this->runtime();
        $runtime['enabled_features'] = [];
        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertContains('forms_selection_conflict', $result['findings']);
        $this->assertSame('blocked', $result['status']);
    }

    public function test_missing_owned_environment_keys_block_selected_capability(): void
    {
        $runtime = $this->runtime();
        $runtime['client_environment_keys'] = ['ENGAGE_SEO_CORE_FORMS_URL'];
        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('recipient_environment_key_not_declared', $result['findings']);
    }

    public function test_existing_mismatched_id_or_destination_is_never_overwritten(): void
    {
        $runtime = $this->runtime();
        $runtime['core_donations'] = [
            'client_id' => 'different_caller',
            'signing_secret' => str_repeat('S', 40),
            'base_url' => 'http://wrong-core.test',
        ];
        $result = $this->inspect('donations.external_checkout', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('recipient_caller_identity_mismatch', $result['findings']);
        $this->assertContains('recipient_core_origin_mismatch', $result['findings']);
    }

    public function test_invalid_existing_secret_blocks_without_leaking_value(): void
    {
        $runtime = $this->runtime();
        $secret = 'bad-secret-do-not-report';
        $runtime['core_forms']['signing_secret'] = $secret;
        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('recipient_secret_invalid', $result['findings']);
        $this->assertStringNotContainsString($secret, json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_wrong_client_and_environment_are_redacted_blockers(): void
    {
        $runtime = $this->runtime();
        $runtime['client_key'] = 'other-client';
        $runtime['environment'] = 'production';
        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('target_identity_mismatch', $result['findings']);
    }

    public function test_source_origin_must_match_effective_site_url(): void
    {
        $runtime = $this->runtime();
        $runtime['site_origin'] = 'http://unexpected.test';
        $result = $this->inspect('donations.external_checkout', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('source_origin_mismatch', $result['findings']);
    }

    public function test_deployed_environment_rejects_http_even_when_configuration_matches(): void
    {
        $runtime = $this->runtime();
        $runtime['environment'] = 'staging';
        $result = $this->inspector->inspect(
            'forms.external_intake', 'seo_forms', 'fixture-seo', 'staging',
            'http://fixture-seo.test', 'http://webhooks.core.test', $runtime,
        );
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('invalid_pair_origin', $result['findings']);
    }

    public function test_unsupported_route_and_invalid_caller_are_blocked(): void
    {
        $result = $this->inspector->inspect(
            'unknown.capability', 'bad caller', 'fixture-seo', 'local',
            'http://fixture-seo.test', 'http://webhooks.core.test', $this->runtime(),
        );
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('unsupported_pairing_route', $result['findings']);
        $this->assertNull($result['caller_id']);
    }

    public function test_malformed_form_evidence_blocks_instead_of_inventing_selection(): void
    {
        $runtime = $this->runtime();
        $runtime['form_bindings'] = ['some_form' => ['enabled' => 'yes']];
        $result = $this->inspect('forms.external_intake', $runtime);
        $this->assertSame('blocked', $result['status']);
        $this->assertContains('form_binding_evidence_invalid', $result['findings']);
    }

    /** @return array<string, mixed> */
    private function runtime(): array
    {
        return [
            'client_key' => 'fixture-seo',
            'environment' => 'local',
            'site_origin' => 'http://fixture-seo.test',
            'enabled_features' => ['forms'],
            'form_bindings' => ['contact_form' => ['enabled' => true]],
            'donations_enabled' => true,
            'nonprofit_provider_selected' => true,
            'client_environment_keys' => [
                'ENGAGE_SEO_CORE_FORMS_CLIENT_ID',
                'ENGAGE_SEO_CORE_FORMS_SIGNING_SECRET',
                'ENGAGE_SEO_CORE_FORMS_URL',
                'ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID',
                'ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET',
                'ENGAGE_SEO_CORE_DONATIONS_URL',
            ],
            'core_forms' => [
                'client_id' => null,
                'signing_secret' => null,
                'base_url' => null,
            ],
            'core_donations' => [
                'client_id' => null,
                'signing_secret' => null,
                'base_url' => null,
            ],
        ];
    }

    /** @param array<string, mixed>|null $runtime @return array<string, mixed> */
    private function inspect(string $purpose, ?array $runtime = null): array
    {
        return $this->inspector->inspect(
            $purpose, 'seo_forms', 'fixture-seo', 'local',
            'http://fixture-seo.test', 'http://webhooks.core.test',
            $runtime ?? $this->runtime(),
        );
    }
}