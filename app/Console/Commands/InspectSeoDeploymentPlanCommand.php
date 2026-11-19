<?php

namespace App\Console\Commands;

use App\Support\Deployment\SeoDeploymentPlanRegistry;
use App\Support\Features\FeatureManager;
use App\Support\Clients\ClientPackageLoader;
use Illuminate\Console\Command;
use Throwable;

/** Selected-client declarations only; this command never changes runtime state. */
final class InspectSeoDeploymentPlanCommand extends Command
{
    protected $signature = 'engage:deployment-plan {--json} {--expected-client=} {--expected-environment=}';
    protected $description = 'Inspect package-owned SEO deployment requirements without provisioning them.';

    public function handle(
        SeoDeploymentPlanRegistry $registry,
        FeatureManager $features,
        ClientPackageLoader $packages,
    ): int {
        try {
            $client = (string) $this->option('expected-client');
            $environment = (string) $this->option('expected-environment');
            if (preg_match('/\A[a-z][a-z0-9_-]{0,127}\z/D', $client) !== 1
                || ! in_array($environment, ['local', 'staging', 'production'], true)
                || config('client.key') !== $client || app()->environment() !== $environment) {
                throw new \RuntimeException('Invalid selected SEO runtime identity.');
            }
            $providers = array_values(array_unique(array_merge(
                $packages->providerClasses(), $features->providerClasses(),
            )));
            $result = $registry->inspect($providers, $features->enabledKeys());
            $result['client_key'] = $client;
            $result['environment'] = $environment;
            $result['status'] = $result['declared_complete'] ? 'declarations_complete' : 'blocked';
        } catch (Throwable) {
            $result = [
                'schema' => 1, 'platform' => 'seo', 'status' => 'blocked',
                'findings' => ['selected_seo_deployment_plan_unavailable'],
                'declared_complete' => false, 'runtime_verified' => false,
                'writes_allowed' => false,
            ];
        }
        $this->line(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return $result['status'] === 'declarations_complete' ? self::SUCCESS : self::FAILURE;
    }
}
