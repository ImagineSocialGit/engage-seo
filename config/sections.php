<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reusable public section components
    |--------------------------------------------------------------------------
    |
    | This is the platform registry for section component keys referenced by
    | config-owned page definitions. Client repositories may override the view
    | for a registered component at resources/views/sections/{component}.blade.php
    | through the explicit client view namespace.
    |
    | Themes are shared semantic presentation modes. Layouts are intentionally
    | component-specific so setup validation can catch unsupported combinations.
    |
    */

    'themes' => [
        'default',
        'surface',
        'inverse',
        'accent',
    ],

    'available' => [
        'content' => [
            'view' => 'sections.content',
            'layouts' => [
                'default',
                'narrow',
                'wide',
                'statement',
            ],
        ],

        'hero' => [
            'view' => 'sections.hero',
            'layouts' => [
                'default',
                'media-right',
                'media-left',
                'media-dominant-right',
                'media-dominant-left',
                'centered',
            ],
        ],

        'content-split' => [
            'view' => 'sections.content-split',
            'layouts' => [
                'default',
                'media-right',
                'media-left',
            ],
        ],

        'card-grid' => [
            'view' => 'sections.card-grid',
            'layouts' => [
                'default',
                'two',
                'three',
                'four',
                'balanced-five',
                'editorial',
                'editorial-two',
            ],
        ],

        'steps' => [
            'view' => 'sections.steps',
            'layouts' => [
                'default',
                'two',
                'three',
            ],
        ],

        'cta' => [
            'view' => 'sections.cta',
            'layouts' => [
                'default',
                'centered',
                'split',
                'inline',
            ],
        ],

        'media-embed' => [
            'view' => 'sections.media-embed',
            'layouts' => [
                'default',
                'wide',
                'split',
            ],
        ],

        'stats' => [
            'view' => 'sections.stats',
            'layouts' => [
                'default',
                'three',
                'four',
                'strip',
            ],
        ],

        'testimonials' => [
            'view' => 'sections.testimonials',
            'layouts' => [
                'default',
                'two',
                'three',
            ],
        ],

        'faq' => [
            'view' => 'sections.faq',
            'layouts' => [
                'default',
                'narrow',
                'two-column',
            ],
        ],
    ],
];