<?php

return [
    // No form is public on a site until explicitly bound by its client repository.
    'bindings' => [],
    'turnstile' => [
        'site_key' => env('ENGAGE_SEO_FORMS_TURNSTILE_SITE_KEY'),
        'secret_key' => env('ENGAGE_SEO_FORMS_TURNSTILE_SECRET_KEY'),
        'timeout_seconds' => 5,
        'max_age_seconds' => 300,
    ],
];