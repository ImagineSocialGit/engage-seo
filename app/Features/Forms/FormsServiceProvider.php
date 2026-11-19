<?php

namespace App\Features\Forms;

use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
use App\Support\Deployment\SeoDeploymentPlanRegistry;
use App\Integrations\EngageCore\Services\CachedEngageCoreFormsClient;
use App\Integrations\EngageCore\Services\HttpEngageCoreFormsClient;
use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;

final class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            EngageCoreFormsClient::class,
            fn ($app): EngageCoreFormsClient => new CachedEngageCoreFormsClient(
                client: $app->make(HttpEngageCoreFormsClient::class),
                cache: $app->make(CacheManager::class),
            ),
        );
    }

    public function boot(): void
    {
        // Loaded only when this feature is enabled by the selected SEO client.
        $formBindings = config('site_forms.bindings', []);
        $formsEnabled = is_array($formBindings) && collect($formBindings)
            ->contains(static fn ($binding) => is_array($binding) && ($binding['enabled'] ?? false) === true);
        $this->app->make(SeoDeploymentPlanRegistry::class)->register(
            self::class, 'forms',
            $formsEnabled ? [
                ['key' => 'ENGAGE_SEO_CORE_FORMS_URL', 'secret' => false],
                ['key' => 'ENGAGE_SEO_CORE_FORMS_CLIENT_ID', 'secret' => false],
                ['key' => 'ENGAGE_SEO_CORE_FORMS_SIGNING_SECRET', 'secret' => true],
                ['key' => 'ENGAGE_SEO_FORMS_TURNSTILE_SITE_KEY', 'secret' => false],
                ['key' => 'ENGAGE_SEO_FORMS_TURNSTILE_SECRET_KEY', 'secret' => true],
            ] : [],
            $formsEnabled ? ['forms.external_intake_setup'] : [],
            $formsEnabled ? ['forms.external_metadata_get', 'forms.external_submission_post'] : [],
            $formsEnabled ? ['forms.provider_route_check', 'forms.cross_platform_signing_check'] : [],
        );
        $this->loadRoutesFrom(__DIR__.'/../../../routes/site-forms.php');
    }
}