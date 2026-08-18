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

## Production safety

Do not enable `DEV_DESTRUCTIVE_COMMANDS_ENABLED` in staging or production.

The reset and refresh scripts also require Laravel to resolve `APP_ENV=local`.