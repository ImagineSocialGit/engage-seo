# Client Configuration

## Selection

The root environment selects one client:

```env
CLIENT_KEY=[CLIENT_KEY]
```

A blank `CLIENT_KEY` leaves the application in its generic platform state.

A selected key resolves to:

```text
clients/[CLIENT_KEY]/
```

The key must:

- start with a lowercase letter or number;
- contain only lowercase letters, numbers, hyphens, and underscores.

## Environment ownership

Environment ownership is explicit rather than based on whichever `.env` happens to load first.

Root `.env` owns machine/process infrastructure such as:

```text
APP_ENV / APP_DEBUG / APP_KEY
CLIENT_KEY
DB_CONNECTION / DB_HOST / DB_PORT
logging
cache/session/queue drivers
Redis host/port/database indexes
filesystem transport choice
local destructive-operation gate
```

Selected client `.env` currently owns:

```text
APP_URL
DB_DATABASE
DB_USERNAME
DB_PASSWORD
CACHE_PREFIX
REDIS_PREFIX
SESSION_DOMAIN
```

When a non-cached application boots with a selected client, Engage SEO:

1. lets Laravel load the process/root environment;
2. resolves `CLIENT_KEY`;
3. validates the selected client directory;
4. parses `clients/[CLIENT_KEY]/.env`;
5. rejects environment keys that are not registered as client-owned;
6. clears every registered client-owned value from the loaded process/root environment;
7. applies the selected client's values;
8. then lets Laravel load application configuration.

This prevents a stale root value such as `APP_URL` or `DB_DATABASE` from silently overriding the selected client.

Do not keep selected-client-owned keys in root `.env` once a client is selected. `setup:validate` reports that ownership conflict.

New client-owned environment keys are added only when a platform Feature or integration establishes and documents the corresponding runtime seam.

## Configuration cache behavior

A cached Laravel configuration contains the fully resolved selected-client environment and merged PHP configuration from the client that was active when the cache was built.

When configuration is cached, Engage SEO does not re-read the selected client `.env` or merge client PHP config again during normal bootstrap.

Therefore, changing clients or changing client environment/configuration requires rebuilding cached configuration:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not switch `CLIENT_KEY` underneath an existing configuration cache and expect runtime client swapping.

## Test isolation

The PHPUnit environment forces `CLIENT_KEY` blank.

Tests must create/configure their own generic test client state when they need to exercise client behavior. A developer's currently selected real client must not leak into the automated test suite.

## PHP configuration ownership

Platform defaults live in:

```text
config/**
```

The selected client may contribute matching files under:

```text
clients/[CLIENT_KEY]/config/**
```

Client configuration merges over platform defaults by config path.

Associative arrays merge recursively.

List/numeric arrays replace the platform list rather than appending to it.

This avoids accidental duplicate Feature lists, navigation lists, or other ordered client configuration.

## Client identity

Required client file:

```text
clients/[CLIENT_KEY]/config/client.php
```

Foundation shape:

```php
<?php

return [
    'name' => '[CLIENT]',
    'key' => '[CLIENT_KEY]',
    'timezone' => 'America/Chicago',
    'vertical' => null,
];
```

The configured key must exactly match the selected `CLIENT_KEY`.

## Features

Client Feature selection lives in:

```text
clients/[CLIENT_KEY]/config/features.php
```

Shape:

```php
<?php

return [
    'enabled' => [
        // 'blog',
        // 'services',
    ],

    'disabled' => [
        // Remove a vertical default when needed.
    ],
];
```

Effective enabled Features are:

```text
selected vertical default Features
+ explicitly enabled client Features
- explicitly disabled client Features
```

Unknown Features fail validation rather than being silently ignored.

## Verticals

The client selects a vertical through:

```text
client.vertical
```

A null value means no vertical.

Platform-registered Verticals live in:

```text
config/verticals.php
```

A Vertical may contribute `default_features`, but should not duplicate generic Feature runtime behavior.

Unknown Verticals fail validation.

## Public site shell

Every client scaffold includes:

```text
clients/[CLIENT_KEY]/config/site.php
```

This file owns client-specific public presentation data such as:

```text
site name
logo reference and alt fallback
header/navigation/footer preferences
navigation items and primary CTA
semantic theme-token overrides
```

The effective site name resolves from:

```text
site.name
client.name
app.name
```

A minimal client shape is:

```php
<?php

return [
    'name' => '[CLIENT]',

    'brand' => [
        'logo' => null,
        'logo_alt' => null,
    ],

    'shell' => [
        'navigation' => [
            'items' => [
            ],

            'primary_cta' => null,
        ],

        'footer' => [
            'items' => [
            ],
        ],
    ],
];
```

The platform provides neutral theme defaults. Clients override only the semantic tokens they need.

Do not put large Tailwind class strings into client theme configuration.

See:

```text
docs/architecture/site-shell-theme.md
```

for navigation, theme-token, and validation contracts.

## Public pages

Static public page definitions belong under:

```text
clients/[CLIENT_KEY]/config/pages/*.php
```

Each page definition declares its own public `path`.

The configuration filename/key is an internal identifier and does not determine the URL.

Example shape:

```php
<?php

return [
    'path' => '/about',

    'meta' => [
        'title' => '...',
        'description' => '...',
        'indexable' => true,
    ],

    'sections' => [
        [
            'id' => 'introduction',
            'component' => 'content',
            'props' => [
                // Component-specific data.
            ],
        ],
    ],
];
```

Page sections always normalize to:

```text
id
component
theme
layout
overrides
props
```

See `docs/architecture/public-rendering.md` for the complete rendering and metadata contract.

## Client view overrides

The selected client is registered under the explicit Blade namespace:

```text
client::
```

The public rendering foundation currently supports automatic overrides only for:

```text
resources/views/pages/public.blade.php
resources/views/sections/{registered-component}.blade.php
```

Arbitrary client views do not silently replace platform views.

The generic header, navigation, and footer are configured through `config/site.php`; they are not automatically replaced by similarly named client Blade files.

## Setup validation

After selecting/configuring a client, run:

```bash
php artisan setup:validate
```

The validator checks structural/configuration contracts including:

- a selected client exists;
- required client config/environment/page directories exist;
- `config/site.php` exists and returns an array;
- the selected client timezone is valid;
- selected Vertical and Feature keys are known;
- root `.env` does not contain selected-client-owned keys;
- client `.env` does not contain root-owned/unknown keys;
- required client environment keys are present;
- required URL/database identity values are structurally usable;
- page paths and page contracts are valid;
- registered reusable section views exist;
- site identity, navigation, shell, and theme contracts are valid.

The validator does not test external provider connectivity or make client-specific business/content assertions.

## Future client override seams

Structured data, media pipelines, richer section components, Feature-specific presentation, and Core integrations will be added only through documented seams as those platform capabilities are implemented.

Do not assume arbitrary files placed in a client repository are automatically loaded.