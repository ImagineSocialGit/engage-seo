<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engage SEO Verticals
    |--------------------------------------------------------------------------
    |
    | Verticals are curated composition layers above reusable Features.
    |
    | The executable contract is intentionally narrow today:
    |
    | - name: internal human-readable vertical name;
    | - default_features: reusable Features enabled by default for clients that
    |   select the vertical.
    |
    | Clients remain authoritative and may add or disable Features explicitly.
    |
    | Do not put client copy, branding, product/service records, locations,
    | licensing/compliance text, claims, external IDs, or credentials here.
    |
    | Additional vertical terminology, page/content presets, or field schemas
    | should be introduced only when repeated client implementations establish
    | a real reusable contract.
    |
    */

    'available' => [
        'mortgage' => [
            'name' => 'Mortgage',
            'default_features' => [
                'services',
                'locations',
                'blog',
            ],
        ],

        'pets' => [
            'name' => 'Pets',
            'default_features' => [
                'services',
                'locations',
                'blog',
            ],
        ],
    ],
];