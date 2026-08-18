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

Environment loading occurs in this order:

```text
1. process/server environment
2. root .env
3. selected client .env
```

Existing environment values are not overwritten by later files.

That produces the intended ownership split:

```text
Root .env
    application/process infrastructure
    APP_ENV / APP_DEBUG / APP_KEY
    CLIENT_KEY
    connection drivers/hosts/ports
    logging
    shared queue/cache/session behavior
    destructive-local-operation gate

Client .env
    APP_URL
    database name and credentials
    client-specific namespaces/prefixes
    client storage/provider credentials
    future client integration credentials
```

Do not keep a root value for a setting that is intended to vary by client, because the root value takes precedence.

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

## Future client override seams

Client views, page definitions, theme configuration, assets, structured data, navigation, and Core integrations will be added only through documented seams as those platform capabilities are implemented.

Do not assume arbitrary files placed in a client repository are automatically loaded.