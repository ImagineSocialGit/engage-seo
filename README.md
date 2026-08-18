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
docs/architecture/public-rendering.md
docs/architecture/site-shell-theme.md
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

Root `.env` owns process/machine infrastructure. The selected client `.env` owns only explicitly registered client-scoped environment values.

When a client is selected, stale root values for registered client-owned keys are cleared before the selected client environment is applied.

After selecting or changing a client, run:

```bash
php artisan optimize:clear
php artisan setup:validate
```

Rebuild configuration cache deliberately for the selected client when deployment uses cached configuration.

## Public site shell

Client public-site identity and shell configuration live in:

```text
clients/[CLIENT_KEY]/config/site.php
```

The platform provides generic semantic header/navigation/footer rendering and neutral theme defaults.

Clients configure:

```text
site name
brand logo reference
navigation
primary CTA
footer navigation
shell enablement
semantic theme-token overrides
```

Theme configuration maps to a fixed set of CSS custom properties rather than storing large Tailwind class strings in PHP config.

## Public pages

Static public pages are config-owned:

```text
clients/[CLIENT_KEY]/config/pages/*.php
```

Each page declares an explicit public path and resolves into one normalized page, metadata, and section contract.

The platform public renderer uses Laravel's fallback routing so future explicit Feature routes remain separate from generic static-page resolution.

See:

```text
docs/architecture/public-rendering.md
```

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

The foundation currently establishes client selection/configuration, explicit root/client environment ownership, Feature/Vertical composition, client scaffolding, setup validation, config-owned public page rendering, normalized metadata/section contracts, explicit client view seams, a normalized public site shell/theme contract, documentation, and safe local database lifecycle tooling.

Richer reusable section components, sitemap/schema/redirect infrastructure, media pipelines, CMS Features, and Engage Core integration implementations are intentionally added in later slices.