<?php

namespace App\Console\Commands;

use App\Support\Clients\ClientEnvironmentDefinition;
use App\Support\Deployment\Credentials\SeoCredentialInstallationContract;
use App\Support\Deployment\Credentials\SeoCredentialInstallationRegistry;
use App\Support\Deployment\Credentials\SeoSelectedCredentialEnvironment;
use Illuminate\Console\Command;
use Throwable;

/** Read-only, package-owned SEO recipient installation-target evidence. */
final class InspectSeoCredentialInstallationCommand extends Command
{
    protected $signature = 'engage:credential-installation:inspect
        {purpose : Internal connection purpose}
        {--caller-id= : Stable approved caller identity}
        {--source-origin= : Approved SEO site origin}
        {--destination-origin= : Approved destination platform origin}
        {--expected-client= : Selected SEO client key}
        {--expected-environment= : Selected environment}';

    protected $description = 'Inspect package-declared credential installation targets without changing credentials.';

    public function handle(
        SeoCredentialInstallationRegistry $registry,
        SeoCredentialInstallationContract $inspector,
        SeoSelectedCredentialEnvironment $environment,
    ): int {
        try {
            $client = (string) $this->option('expected-client');
            // Validate before computing any filesystem path or loading PHP from
            // a selected-client configuration directory.
            if (preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $client) !== 1) {
                throw new \RuntimeException('Invalid selected client.');
            }
            $definition = $registry->find((string) $this->argument('purpose'));
            // Never obtain a binding from caller-specified configuration paths.
            $binding = $definition === null ? null : config($definition->configurationPath);
            // First validate the selected file/config directory before any
            // package environment definition PHP is loaded by this command.
            $persisted = $environment->read(base_path(), $client);
            $directory = base_path('client/'.$client);
            $result = $inspector->inspect(
                purpose: (string) $this->argument('purpose'),
                callerId: (string) $this->option('caller-id'),
                expectedClient: $client,
                expectedEnvironment: (string) $this->option('expected-environment'),
                expectedSourceOrigin: (string) $this->option('source-origin'),
                expectedDestinationOrigin: (string) $this->option('destination-origin'),
                runtime: [
                    'client_key' => config('client.key'),
                    'environment' => app()->environment(),
                    'site_origin' => config('app.url'),
                    'client_environment_keys' => ClientEnvironmentDefinition::clientOwnedKeys($directory),
                    'binding' => $binding,
                ],
                persisted: $persisted,
            );
        } catch (Throwable) {
            $result = [
                'schema' => 1,
                'platform' => 'seo',
                'status' => 'blocked',
                'findings' => ['installation_inspection_unavailable'],
                'installation_target_recognized' => false,
                'assignments' => [],
                'credential_equality_verified' => false,
                'delivery_allowed' => false,
                'writes_allowed' => false,
            ];
        }

        $this->line((string) json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return $result['status'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}