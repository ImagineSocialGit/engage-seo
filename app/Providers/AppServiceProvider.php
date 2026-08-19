<?php

namespace App\Providers;

use App\Console\Commands\AuditSeoMigrationCommand;
use App\Console\Commands\ValidateSetupCommand;
use App\Support\Clients\ClientConfigLoader;
use App\Support\Features\FeatureManager;
use App\Support\Pages\PageMetaResolver;
use App\Support\Pages\PageRepository;
use App\Support\Sections\SectionManager;
use App\Support\Seo\RedirectRepository;
use App\Support\Seo\SeoExtensionRegistry;
use App\Support\Seo\Migration\LegacyUrlInventoryRepository;
use App\Support\Seo\Migration\SeoMigrationAuditor;
use App\Support\Seo\SeoIndexingPolicy;
use App\Support\Seo\SitemapBuilder;
use App\Support\Seo\StructuredDataResolver;
use App\Support\SetupValidation\ClientSetupValidator;
use App\Support\Site\SitePresentationResolver;
use App\Support\Verticals\VerticalManager;
use App\Support\Views\ClientViewNamespaceRegistrar;
use App\Support\Views\PageViewResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientConfigLoader::class);
        $this->app->singleton(VerticalManager::class);
        $this->app->singleton(FeatureManager::class);
        $this->app->singleton(SeoExtensionRegistry::class);
        $this->app->singleton(SeoIndexingPolicy::class);
        $this->app->singleton(RedirectRepository::class);
        $this->app->singleton(LegacyUrlInventoryRepository::class);
        $this->app->singleton(SeoMigrationAuditor::class);
        $this->app->singleton(PageMetaResolver::class);
        $this->app->singleton(PageRepository::class);
        $this->app->singleton(SitemapBuilder::class);
        $this->app->singleton(StructuredDataResolver::class);
        $this->app->singleton(SectionManager::class);
        $this->app->singleton(SitePresentationResolver::class);
        $this->app->singleton(ClientViewNamespaceRegistrar::class);
        $this->app->singleton(PageViewResolver::class);
        $this->app->singleton(ClientSetupValidator::class);

        $this->app->make(ClientConfigLoader::class)->load();

        $features = $this->app->make(FeatureManager::class);

        $features->assertValid();
        $features->registerProviders();
    }

    public function boot(
        ClientViewNamespaceRegistrar $clientViews,
    ): void {
        $clientViews->register();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditSeoMigrationCommand::class,
                ValidateSetupCommand::class,
            ]);
        }
    }
}