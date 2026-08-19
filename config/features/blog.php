<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Blog / Learning Center Feature
    |--------------------------------------------------------------------------
    |
    | The public article runtime is database-backed. The base path and generic
    | archive presentation are client-configurable. A client may call the
    | experience Blog, Learning Center, Resources, News, or another appropriate
    | label without changing the Feature runtime.
    |
    */

    'path' => '/blog',

    'posts_per_page' => 12,

    'category_indexable' => true,

    'index' => [
        'title' => 'Blog',
        'meta_title' => null,
        'meta_description' => null,
        'eyebrow' => null,
        'intro' => null,
        'actions' => [],
        'indexable' => true,
        'featured_limit' => 4,
        'featured_title' => null,
        'categories_title' => null,
        'footer_cta' => null,
    ],
];