<?php

namespace App\Features\Forms;

use App\Integrations\EngageCore\Contracts\EngageCoreFormsClient;
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
        // Loaded only when the selected SEO client enables the Forms feature.
        $this->loadRoutesFrom(__DIR__.'/../../../routes/site-forms.php');
    }
}