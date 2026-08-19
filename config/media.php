<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Static client media
    |--------------------------------------------------------------------------
    |
    | Client-owned source images are processed into public/media by the
    | platform media build. Runtime rendering reads the generated manifest
    | rather than inferring variant filenames from source paths.
    |
    */

    'manifest_path' => 'public/media/manifest.json',

    /*
     * Default public path for locally deployed generated media.
     */
    'public_prefix' => '/media',

    /*
     * Optional client override for generated media mirrored to another public
     * origin, such as a CDN. May be an absolute http/https URL or absolute
     * site path. Null uses public_prefix.
     */
    'base_url' => null,
];