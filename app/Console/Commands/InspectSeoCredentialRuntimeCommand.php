<?php

namespace App\Console\Commands;

use App\Support\Clients\ClientEnvironmentDefinition;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeProbe;
use App\Support\Deployment\Credentials\SeoCredentialRuntimeFailure;
use App\Support\Deployment\Credentials\SeoSelectedCredentialEnvironment;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Throwable;

/** Console-only runtime evidence from package-owned configuration declarations. */
final class InspectSeoCredentialRuntimeCommand extends Command
{
    protected $signature = 'engage:credential-runtime:inspect
        {purpose? : Package-owned internal connection purpose}
        {--inventory : List all active package-owned runtime declarations}
        {--caller-id= : Expected caller identity}
        {--challenge= : Fresh controller-generated 32-byte hexadecimal nonce}
        {--expected-client= : Expected selected SEO client}
        {--expected-environment= : Expected deployment environment}';

    protected $description = 'Inspect internal runtime bindings or prove effective package configuration.';

    public function handle(
        SeoCredentialRuntimeProbe $probe,
        SeoCredentialInstallationRegistry $registry,
        SeoSelectedCredentialEnvironment $environment,
    ): int {
        $stage = 'target_selection';
        try {
            $client = (string) $this->option('expected-client');
            if (preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $client) !== 1
                || config('client.key') !== $client
                || app()->environment() !== $this->option('expected-environment')
                || ! in_array(app()->environment(), ['local', 'staging', 'production'], true)
            ) {
                throw new SeoCredentialRuntimeFailure('runtime_target_identity_mismatch');
            }
            // Validate selected-client directories before loading ownership PHP.
            $stage = 'selected_environment';
            $persisted = $environment->read(base_path(), $client);
            $runtime = [
                'client_key' => config('client.key'),
                'environment' => app()->environment(),
                'site_origin' => config('app.url'),
                'client_environment_keys' => ClientEnvironmentDefinition::clientOwnedKeys(base_path('client/'.$client)),
            ];
            if ($this->option('inventory')) {
                if ($this->argument('purpose') !== null || $this->option('challenge') !== null
                    || $this->option('caller-id') !== null
                ) {
                    throw new SeoCredentialRuntimeFailure('runtime_inventory_arguments_invalid');
                }
                $stage = 'runtime_inventory';
                $result = $probe->inventory($runtime);
            } else {
                $stage = 'runtime_proof';
                $purpose = (string) $this->argument('purpose');
                $definition = $registry->find($purpose);
                $runtime['binding'] = $definition === null ? null : config($definition->configurationPath);
                $root = Dotenv::createArrayBacked(base_path(), '.env')->safeLoad();
                $result = $probe->prove($purpose, (string) $this->option('caller-id'),
                    (string) $this->option('challenge'), $runtime, $persisted, $root);
            }
        } catch (Throwable $error) {
            $result = [
                'schema' => 1, 'platform' => 'seo', 'status' => 'blocked',
                'phase' => $stage,
                'findings' => [SeoCredentialRuntimeFailure::finding($error, $stage)],
                'credential_equality_verified' => false,
                'network_verified' => false, 'writes_allowed' => false,
            ];
        }

        $this->line((string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $result['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}