<?php

use App\Features\Blog\BlogServiceProvider;
use App\Features\Forms\FormsServiceProvider;
use App\Features\Locations\LocationsServiceProvider;
use App\Features\Services\ServicesServiceProvider;

return [
    /* Optional reusable SEO features. Enabled selection remains client-owned. */
    'available' => [
        'services' => ['provider' => ServicesServiceProvider::class],
        'locations' => ['provider' => LocationsServiceProvider::class],
        'blog' => ['provider' => BlogServiceProvider::class],
        'forms' => ['provider' => FormsServiceProvider::class],
    ],
    'enabled' => [],
    'disabled' => [],
];