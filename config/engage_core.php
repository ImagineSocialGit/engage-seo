<?php

return [
    // Secret credentials belong to the selected site's environment, not client source.
    'base_url' => env('ENGAGE_SEO_CORE_FORMS_URL'),
    'client_key' => env('ENGAGE_SEO_CORE_FORMS_CLIENT_ID'),
    'signing_secret' => env('ENGAGE_SEO_CORE_FORMS_SIGNING_SECRET'),
    'donations' => [
        'base_url' => env('ENGAGE_SEO_CORE_DONATIONS_URL'),
        'client_id' => env('ENGAGE_SEO_CORE_DONATIONS_CLIENT_ID'),
        'signing_secret' => env('ENGAGE_SEO_CORE_DONATIONS_SIGNING_SECRET'),
    ],
    'forms' => [
        'path' => '/forms',
        'timeout_seconds' => 5,
        'connect_timeout_seconds' => 2,
        'retry_attempts' => 2,
        'retry_sleep_milliseconds' => 150,
        'cache_seconds' => 300,
        'cache_store' => null,
    ],
];