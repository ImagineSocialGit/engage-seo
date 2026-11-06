<?php

return [
    // Explicitly opt in on the developer workstation. Rendering is also gated
    // on APP_ENV=local and APP_DEBUG=true; other environments cannot enable it.
    'source_tooltips' => env('ENGAGE_SEO_SOURCE_TOOLTIPS', false),
];