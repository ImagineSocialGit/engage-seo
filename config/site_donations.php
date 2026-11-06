<?php

return [
    // Opt-in per selected SEO client; public pages remain client-owned.
    'enabled' => false,
    'page_path' => '/donate',
    'minimum_amount_cents' => 500,
    'maximum_amount_cents' => 100000000,
    'suggested_amounts' => [25, 50, 100, 250],
    'verification_action' => 'donations',
];