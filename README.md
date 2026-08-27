# Engage SEO

Engage SEO is a reusable Laravel platform for client-facing business and SEO websites.

It is designed for:

- fast startup for new client sites;
- separately versioned client repositories;
- lightweight optional Features such as Blog, Services, Forms, or Locations;
- optional built-in Vertical defaults such as Mortgage or Pets;
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
docs/architecture/old-platform-migration.md
docs/architecture/platform-boundaries.md
docs/architecture/public-rendering.md
docs/architecture/seo-infrastructure.md
docs/architecture/site-shell-theme.md
docs/architecture/verticals.md
docs/configuration/client-configuration.md
docs/operations/client-lifecycle.md
docs/operations/editorial-promotion.md
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

Optional Vertical:

```bash
./scripts/create-client.sh [CLIENT_KEY] [TIMEZONE] [VERTICAL_KEY]
```

Built-in Vertical keys:

```text
mortgage
pets
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
site name and brand
optional business/contact identity
utility/compliance bar
navigation and primary CTA
structured footer groups and conversion CTA
legal/disclosure content
semantic shell/theme-token overrides
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

## SEO infrastructure

New clients start with production indexing disabled in:

```text
clients/[CLIENT_KEY]/config/site.php
```

Engage SEO permits indexing only for a selected production client with the explicit launch switch enabled.

The platform owns dynamic:

```text
/robots.txt
/sitemap.xml
```

plus config-owned redirects, page JSON-LD, and contribution seams that future Features can use for dynamic sitemap/structured-data entries.

See:

```text
docs/architecture/seo-infrastructure.md
```

## Old-platform SEO migration

Clients replacing an existing public site can enable a source-controlled legacy URL inventory and audit every known path before cutover.

Run:

```bash
php artisan seo:migration:audit
```

Every legacy URL is classified as preserved, redirected, retired, or unaccounted. Redirected entries are verified against the existing `site.seo.redirects` runtime configuration so migration planning does not create a second redirect authority.

See:

```text
docs/architecture/old-platform-migration.md
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

## Editorial promotion

Database-backed editorial Features do not publish by copying staging databases into production. Reviewed state moves through a versioned snapshot:

```bash
# staging
php artisan editorial:export

# production
php artisan editorial:validate /path/to/snapshot.json
php artisan editorial:import /path/to/snapshot.json --force
```

Production creates a protected rollback snapshot automatically before replacement. Runtime credentials and unrelated database tables are never part of the editorial artifact.

See:

```text
docs/operations/editorial-promotion.md
```

## Current implementation status

The foundation currently establishes client selection/configuration, explicit root/client environment ownership, Feature/Vertical composition, client scaffolding, setup validation, config-owned public page rendering, normalized metadata/section contracts, explicit client view seams, reusable public sections, responsive static media, a normalized business site shell/theme contract, production-gated indexing, dynamic robots/sitemap output, redirects, JSON-LD contribution seams, old-platform SEO migration auditing, reusable Services and Locations Features, a database-backed Blog / Learning Center public runtime, versioned staging-to-production editorial promotion, MySQL-based database testing, documentation, and safe local database lifecycle tooling.

A staging-only browser editorial UI, vertical defaults, and Engage Core integration implementations are intentionally added in later slices.