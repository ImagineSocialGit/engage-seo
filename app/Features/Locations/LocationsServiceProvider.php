<?php

namespace App\Features\Locations;

use App\Support\SetupValidation\SetupValidationRegistry;
use Illuminate\Support\ServiceProvider;

final class LocationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/features/locations.php'),
            'features.locations',
        );

        $this->app->singleton(LocationCatalog::class);
    }

    public function boot(
        SetupValidationRegistry $setupValidation,
    ): void {
        config()->set('sections.available.locations', [
            'view' => 'features.locations.catalog',
            'layouts' => [
                'default',
                'two',
                'three',
                'four',
            ],
        ]);

        $setupValidation->register(LocationCatalog::class);
    }
}