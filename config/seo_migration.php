<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Old platform SEO migration
    |--------------------------------------------------------------------------
    |
    | Enable this only when the selected client is replacing an existing
    | public site whose legacy URLs must be accounted for before cutover.
    |
    */

    'enabled' => false,

    /*
     * Client-relative source-controlled inventory. Supported format in this
     * foundation is TSV with the exact columns:
     *
     * path    outcome    target    notes
     *
     * Outcomes are: preserved, redirected, retired, or blank/unaccounted.
     */
    'inventory_path' => 'resources/migration/legacy-urls.tsv',
];