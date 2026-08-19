<?php

use App\Features\Blog\BlogServiceProvider;
use App\Features\Locations\LocationsServiceProvider;
use App\Features\Services\ServicesServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Engage SEO Features
    |--------------------------------------------------------------------------
    |
    | Features are optional reusable site capabilities such as Blog, Services,
    | Forms, Testimonials, or Locations.
    |
    | `available` is the platform registry.
    | `enabled` is the explicit selected-client list.
    | `disabled` removes a feature that would otherwise be enabled by a vertical
    | default or by client configuration.
    |
    | Features should stay lightweight. A feature owns only the routes, models,
    | services, views, configuration, or persistence it genuinely needs.
    |
    */

    'available' => [
        'services' => [
            'provider' => ServicesServiceProvider::class,
        ],
        'locations' => [
            'provider' => LocationsServiceProvider::class,
        ],
        'blog' => [
            'provider' => BlogServiceProvider::class,
        ],
    ],

    'enabled' => [],

    'disabled' => [],
];