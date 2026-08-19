<?php

namespace App\Features\Services;

use App\Support\SetupValidation\SetupValidationRegistry;
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
        config()->set('sections.available.services', [
            'view' => 'features.services.catalog',
            'layouts' => [
                'default',
                'two',
                'three',
            ],
        ]);

        $setupValidation->register(ServiceCatalog::class);
    }
}