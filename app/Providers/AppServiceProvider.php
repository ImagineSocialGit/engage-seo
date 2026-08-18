<?php

namespace App\Providers;

use App\Console\Commands\ValidateSetupCommand;
use App\Support\Clients\ClientConfigLoader;
use App\Support\Features\FeatureManager;
use App\Support\SetupValidation\ClientSetupValidator;
use App\Support\Verticals\VerticalManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientConfigLoader::class);
        $this->app->singleton(VerticalManager::class);
        $this->app->singleton(FeatureManager::class);
        $this->app->singleton(ClientSetupValidator::class);

        $this->app->make(ClientConfigLoader::class)->load();

        $features = $this->app->make(FeatureManager::class);

        $features->assertValid();
        $features->registerProviders();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ValidateSetupCommand::class,
            ]);
        }
    }
}