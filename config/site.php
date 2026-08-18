<?php

return [
    'name' => null,

    'brand' => [
        'logo' => null,
        'logo_alt' => null,
    ],

    'seo' => [
        'default_description' => null,
        'default_image' => null,
        'default_indexable' => true,
        'open_graph_type' => 'website',
        'twitter_card' => 'summary_large_image',
    ],

    'shell' => [
        'header' => [
            'enabled' => true,
        ],

        'navigation' => [
            'enabled' => true,
            'items' => [],
            'primary_cta' => null,
        ],

        'footer' => [
            'enabled' => true,
            'items' => [],
        ],
    ],

    'theme' => [
        'colors' => [
            'background' => '#ffffff',
            'surface' => '#ffffff',
            'text' => '#111827',
            'muted' => '#4b5563',
            'primary' => '#111827',
            'primary_contrast' => '#ffffff',
            'border' => '#e5e7eb',
            'focus' => '#2563eb',
        ],

        'typography' => [
            'body_font_family' => 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
            'heading_font_family' => 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
        ],

        'layout' => [
            'content_max_width' => '72rem',
        ],

        'radius' => [
            'control' => '0.5rem',
            'surface' => '0.75rem',
        ],
    ],
];