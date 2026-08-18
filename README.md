# Engage SEO

Engage SEO is a reusable Laravel platform for client-facing business and SEO websites.

It is designed for:

- fast startup for new client sites;
- separately versioned client repositories;
- lightweight optional Features such as Blog, Services, Forms, or Locations;
- optional Vertical defaults such as Construction or Pets;
- semantic, accessibility-friendly public HTML;
- strong SEO foundations;
- optional explicit integration with Engage Core without duplicating CRM responsibilities.

## Architecture

```text
Platform
    shared runtime, rendering, SEO, accessibility, tooling

Features
    reusable optional capabilities

Verticals
    industry/category defaults composed from Features

clients/[CLIENT_KEY]
    brand, content, feature selection, overrides, deployment values
```

See:

```text
docs/README.md
docs/architecture/platform-boundaries.md
docs/configuration/client-configuration.md
docs/operations/client-lifecycle.md
```

## Client repositories

The platform repository ignores client directories under:

```text
clients/
```

except for `clients/.gitkeep`.

Each actual client directory may be its own Git repository.

Create a scaffold with:

```bash
./scripts/create-client.sh [CLIENT_KEY] [TIMEZONE]
```

Optional vertical:

```bash
./scripts/create-client.sh [CLIENT_KEY] [TIMEZONE] [VERTICAL_KEY]
```

## Selected client

The root `.env` selects one client:

```env
CLIENT_KEY=[CLIENT_KEY]
```

The selected client contributes:

```text
clients/[CLIENT_KEY]/.env
clients/[CLIENT_KEY]/config/**
```

Root/process environment values take precedence over selected-client environment values.

## Development database operations

Local destructive scripts require:

```env
APP_ENV=local
DEV_DESTRUCTIVE_COMMANDS_ENABLED=true
```

Reset:

```bash
./scripts/dev-reset-client-database.sh
```

Refresh:

```bash
./scripts/dev-refresh-client.sh
```

Both refuse to operate without a selected client and the required safety gates.

## Current implementation status

The foundation currently establishes client selection/configuration, Feature/Vertical composition, client scaffolding, documentation, and safe local database lifecycle tooling.

Page rendering, theme/design systems, CMS Features, SEO metadata, accessibility components, media pipelines, and Engage Core integration implementations are intentionally added in later slices.