<?php

namespace App\Providers;

use App\Support\Clients\ClientConfigLoader;
use App\Support\Features\FeatureManager;
use App\Support\Verticals\VerticalManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClientConfigLoader::class);
        $this->app->singleton(VerticalManager::class);
        $this->app->singleton(FeatureManager::class);

        $this->app->make(ClientConfigLoader::class)->load();

        $features = $this->app->make(FeatureManager::class);

        $features->assertValid();
        $features->registerProviders();
    }

    public function boot(): void
    {
        //
    }
}