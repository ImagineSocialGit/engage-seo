<?php

return [
    'name' => null,

    'brand' => [
        'logo' => null,
        'logo_alt' => null,
    ],

    'seo' => [
        /*
         * Production indexing is an explicit launch switch.
         *
         * Even when true, Engage SEO refuses indexing outside APP_ENV=production
         * and refuses indexing when no client is selected.
         */
        'indexing_enabled' => false,

        'default_description' => null,
        'default_image' => null,
        'default_indexable' => true,
        'open_graph_type' => 'website',
        'twitter_card' => 'summary_large_image',

        /*
         * Sitemap output is available only when the site-wide indexing policy
         * also permits indexing.
         */
        'sitemap_enabled' => true,

        /*
         * Redirects are evaluated only by the generic public-page fallback.
         * Explicit platform/Feature routes therefore retain precedence.
         */
        'redirects' => [],
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

            /*
             * Contrasting section palette used by semantic `inverse` section
             * themes. Clients may override these without supplying CSS classes.
             */
            'inverse_background' => '#111827',
            'inverse_surface' => '#1f2937',
            'inverse_text' => '#ffffff',
            'inverse_muted' => '#d1d5db',
            'inverse_border' => '#374151',
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