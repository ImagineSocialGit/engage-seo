# Client Lifecycle Operations

## Create a client scaffold

Run:

```bash
./scripts/create-client.sh [CLIENT_KEY] [TIMEZONE]
```

Optional vertical:

```bash
./scripts/create-client.sh [CLIENT_KEY] [TIMEZONE] [VERTICAL_KEY]
```

Example:

```bash
./scripts/create-client.sh example-client America/Chicago
```

The script:

- validates the client key;
- validates the timezone;
- validates the optional registered vertical;
- creates the scaffold in a temporary directory;
- creates the public page and supported client-view directories;
- creates the client site-shell configuration;
- creates disabled-by-default old-platform migration config and a legacy URL inventory template;
- validates generated PHP;
- assigns PHP-FPM-readable permissions;
- atomically publishes `clients/[CLIENT_KEY]`.

It does not initialize a Git repository or create a remote.

To make the generated client its own repository:

```bash
cd clients/[CLIENT_KEY]
git init
```

## Client environment

Create the real client environment from the generated example:

```bash
sudo install \
  -o "$(id -un)" \
  -g www-data \
  -m 640 \
  clients/[CLIENT_KEY]/.env.example \
  clients/[CLIENT_KEY]/.env
```

Adjust the group when PHP-FPM uses a different group.

Populate the client URL, database identity/credentials, and client-specific namespaces.

Configure public site identity/navigation/theme values in:

```text
clients/[CLIENT_KEY]/config/site.php
```

Add static public pages under:

```text
clients/[CLIENT_KEY]/config/pages/
```

Then set only the selected key in the platform root `.env`:

```env
CLIENT_KEY=[CLIENT_KEY]
```

Clear cached Laravel state after changing selected-client configuration:

```bash
php artisan optimize:clear
```

Validate the complete selected-client foundation:

```bash
php artisan setup:validate
```

## Root/client environment ownership

Root `.env` owns machine/process values such as:

```text
APP_ENV / APP_DEBUG / APP_KEY
CLIENT_KEY
DB_CONNECTION / DB_HOST / DB_PORT
logging
cache/session/queue drivers
Redis connection infrastructure
filesystem transport choice
```

The selected client `.env` currently owns:

```text
APP_URL
DB_DATABASE
DB_USERNAME
DB_PASSWORD
CACHE_PREFIX
REDIS_PREFIX
SESSION_DOMAIN
```

Do not duplicate a selected-client-owned key in root `.env`.

The selected-client loader explicitly clears stale values for registered client-owned keys before applying `clients/[CLIENT_KEY]/.env`.

`setup:validate` reports root/client ownership violations and unsupported client environment keys.

## Configuration cache and client changes

The configuration cache represents one resolved selected client.

When changing `CLIENT_KEY`, client `.env`, or client PHP config, rebuild the cache:

```bash
php artisan optimize:clear
php artisan config:cache
```

Do not rely on runtime file reloading while configuration is cached.

## Reset a local client database

Destructive database tooling requires both:

```text
APP_ENV=local
DEV_DESTRUCTIVE_COMMANDS_ENABLED=true
```

Run:

```bash
./scripts/dev-reset-client-database.sh
```

The script displays the resolved client, connection, and database and requires an exact typed confirmation before wiping the selected database.

Use `--force` only for deliberate non-interactive local automation:

```bash
./scripts/dev-reset-client-database.sh --force
```

`--force` does not bypass the environment or destructive-operation safety gates.

## Refresh a local client database

Run:

```bash
./scripts/dev-refresh-client.sh
```

This performs the same safety-gated reset, then runs migrations.

Use:

```bash
./scripts/dev-refresh-client.sh --force
```

for deliberate local non-interactive operation.

The foundation refresh does not seed client content. A future install/bootstrap contract may extend this once durable CMS/client-state ownership exists.

## Git isolation

The platform repository tracks:

```text
clients/.gitkeep
```

but ignores:

```text
clients/*
```

except for that placeholder.

Each actual client directory may therefore be its own repository without its files becoming part of the platform repository.

## Old-platform SEO migration

When the client replaces an existing public site, enable:

```php
// clients/[CLIENT_KEY]/config/seo_migration.php

return [
    'enabled' => true,
    'inventory_path' => 'resources/migration/legacy-urls.tsv',
];
```

Populate the source-controlled inventory:

```text
clients/[CLIENT_KEY]/resources/migration/legacy-urls.tsv
```

Use one row for every known old public URL.

Before cutover, run:

```bash
php artisan seo:migration:audit
```

Do not proceed while any URL is unaccounted for or while preserved/redirected outcomes fail validation.

The audit verifies permanent redirect behavior against `site.seo.redirects`; it does not install a second redirect system.

A complete technical audit does not guarantee that a search engine will preserve a particular ranking. It reduces avoidable cutover loss caused by missing URLs, temporary redirects, broken targets, or contradictory canonicals.

See:

```text
docs/architecture/old-platform-migration.md
```

## SEO launch gate

New clients start with production indexing disabled:

```php
'site' => [
    'seo' => [
        'indexing_enabled' => false,
    ],
],
```

The actual client file is:

```text
clients/[CLIENT_KEY]/config/site.php
```

Before enabling indexing:

1. confirm the intended production `APP_URL`;
2. if replacing an existing site, complete `php artisan seo:migration:audit`;
3. confirm canonical metadata and redirects;
4. confirm `/robots.txt` disallows crawling on non-production;
5. confirm the production site is ready to be crawled;
6. set:

   ```php
   'indexing_enabled' => true,
   ```

7. clear/rebuild cached configuration as required by the deployment;
8. run:

   ```bash
   php artisan setup:validate
   ```

In non-production environments, Engage SEO remains non-indexable even if the client switch is accidentally enabled.

## Production safety

Do not enable `DEV_DESTRUCTIVE_COMMANDS_ENABLED` in staging or production.

The reset and refresh scripts also require Laravel to resolve `APP_ENV=local`.