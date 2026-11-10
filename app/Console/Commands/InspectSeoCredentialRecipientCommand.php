<?php

namespace App\Console\Commands;

use App\Support\Deployment\Credentials\SeoCredentialRecipientInspector;
use App\Support\Features\FeatureManager;
use Illuminate\Console\Command;
use Throwable;

/** Internal, read-only selected SEO client evidence for signing-pair review. */
final class InspectSeoCredentialRecipientCommand extends Command
{
    protected $signature = 'engage:credential-recipient:inspect
        {purpose : First-party pairing purpose}
        {--caller-id= : Proposed stable caller identity}
        {--source-origin= : Expected SEO public origin}
        {--core-origin= : Expected Core webhook origin}
        {--expected-client= : Expected selected SEO client key}
        {--expected-environment= : Expected environment}';

    protected $description = 'Inspect selected SEO signing-credential readiness without issuing or reading out secrets.';

    public function handle(
        SeoCredentialRecipientInspector $inspector,
        FeatureManager $features,
    ): int {
        try {
            $result = $inspector->inspect(
                purpose: (string) $this->argument('purpose'),
                callerId: (string) $this->option('caller-id'),
                expectedClient: (string) $this->option('expected-client'),
                expectedEnvironment: (string) $this->option('expected-environment'),
                expectedSourceOrigin: (string) $this->option('source-origin'),
                expectedCoreOrigin: (string) $this->option('core-origin'),
                runtime: [
                    'client_key' => config('client.key'),
                    'environment' => app()->environment(),
                    'site_origin' => config('app.url'),
                    'enabled_features' => $features->enabledKeys(),
                    'form_bindings' => config('site_forms.bindings', []),
                    'donations_enabled' => config('site_donations.enabled', false),
                    'nonprofit_provider_selected' => in_array(
                        'ImagineSocial\\EngageSeoNonprofit\\SeoNonprofitServiceProvider',
                        config('client_packages.providers', []),
                        true,
                    ),
                    'client_environment_keys' => config('client_packages.environment_keys', []),
                    'core_forms' => [
                        'base_url' => config('engage_core.base_url'),
                        'client_id' => config('engage_core.client_key'),
                        'signing_secret' => config('engage_core.signing_secret'),
                    ],
                    'core_donations' => [
                        'base_url' => config('seo_nonprofit.core_donations.base_url'),
                        'client_id' => config('seo_nonprofit.core_donations.client_id'),
                        'signing_secret' => config('seo_nonprofit.core_donations.signing_secret'),
                    ],
                ],
            );
        } catch (Throwable) {
            $this->line((string) json_encode([
                'schema' => 1,
                'platform' => 'seo',
                'status' => 'blocked',
                'findings' => ['inspection_unavailable'],
                'issuance_allowed' => false,
                'writes_allowed' => false,
            ], JSON_THROW_ON_ERROR));

            return self::FAILURE;
        }

        $this->line((string) json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return $result['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}