<?php

namespace App\Features\Services;

use App\Support\SetupValidation\SetupValidationRegistry;
use App\Support\Deployment\SeoDeploymentPlanRegistry;
use Illuminate\Support\ServiceProvider;

final class ServicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/features/services.php'),
            'features.services',
        );

        $this->app->singleton(ServiceCatalog::class);
    }

    public function boot(
        SetupValidationRegistry $setupValidation,
    ): void {
        $this->app->make(SeoDeploymentPlanRegistry::class)->register(
            self::class, 'services', [], [], [], [],
        );

        config()->set('sections.available.services', [
            'view' => 'features.services.catalog',
            'layouts' => [
                'default',
                'two',
                'three',
                'editorial',
                'editorial-two',
            ],
        ]);

        $setupValidation->register(ServiceCatalog::class);
    }
}