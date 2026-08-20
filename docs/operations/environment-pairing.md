# Staging / Production Environment Pairing

## Scope

This document defines the deployment-environment rule for **Engage SEO Sites**.

The platform names are distinct:

1. **Engage Core**
2. **Engage Artist Sites**
3. **Engage SEO Sites**

Do not collapse Artist Sites and SEO Sites into one generic "Engage Sites" product when deployment or integration boundaries matter.

## Canonical domain convention

For a client root domain such as `domain.com`, the standard topology is:

```text
STAGING

Engage SEO Sites
    staging.domain.com

Engage Core CRM
    crm.staging.domain.com
```

```text
PRODUCTION

Engage SEO Sites
    domain.com

Engage Core CRM
    crm.domain.com
```

A client may require a different public hostname topology. The invariant is the environment match, not the literal hostname pattern.

The human-facing CRM hostname is also not automatically the server-to-server API base. A future Core integration must use the endpoint exposed by the current Engage Core integration contract for that environment.

## Environment-pairing invariant

The rule is:

```text
staging Engage SEO Sites
    -> staging Engage Core only

production Engage SEO Sites
    -> production Engage Core only
```

Never permit:

```text
staging SEO -> production Core
production SEO -> staging Core
```

Staging must be able to exercise realistic end-to-end behavior without creating or mutating production CRM state.

## Current executable environment ownership

The current Engage SEO Sites runtime has no implemented Engage Core server-to-server integration variables yet.

Do not invent a Core API base URL, client ID, signing-secret name, route, header, or signing format until the current Core integration contract is implemented in this repository.

The current root environment owns machine/process values including:

```text
APP_ENV
APP_DEBUG
APP_KEY
CLIENT_KEY
DB_CONNECTION
DB_HOST
DB_PORT
logging/cache/session/queue driver choices
Redis connection infrastructure
filesystem transport choice
```

The selected client runtime currently owns:

```text
APP_URL
DB_DATABASE
DB_USERNAME
DB_PASSWORD
CACHE_PREFIX
REDIS_PREFIX
SESSION_DOMAIN
```

Each deployment gets its own real root `.env` and selected-client `.env`.

## Concrete staging example

Platform root `.env` on the staging deployment:

```env
APP_ENV=staging
CLIENT_KEY=example-client
```

Selected client runtime file on staging:

```text
clients/example-client/.env
```

Example values:

```env
APP_URL=https://staging.domain.com
DB_DATABASE=example_client_staging
DB_USERNAME=example_client_staging
DB_PASSWORD=...
CACHE_PREFIX=example-client-staging-cache-
REDIS_PREFIX=example-client-staging-
```

If `SESSION_DOMAIN` is needed, it must describe the staging cookie scope, not the production cookie scope.

The standard matching human-facing Core CRM hostname would be:

```text
crm.staging.domain.com
```

That hostname does not by itself define a Core API endpoint.

## Concrete production example

Platform root `.env` on the production deployment:

```env
APP_ENV=production
CLIENT_KEY=example-client
```

Selected client runtime file on production:

```text
clients/example-client/.env
```

Example values:

```env
APP_URL=https://domain.com
DB_DATABASE=example_client
DB_USERNAME=example_client
DB_PASSWORD=...
CACHE_PREFIX=example-client-production-cache-
REDIS_PREFIX=example-client-production-
```

If `SESSION_DOMAIN` is needed, it must describe the production cookie scope.

The standard matching human-facing Core CRM hostname would be:

```text
crm.domain.com
```

Again, do not derive a server-to-server API base from that hostname without the current Core contract.

## Future Engage Core integration variables

When Engage SEO Sites gains a Core integration, its runtime destination and credentials must be added as explicit supported client-owned environment keys through the platform environment-definition seam.

For every such integration value:

- staging receives a staging-specific value;
- production receives a production-specific value;
- staging credentials are issued separately from production credentials;
- production secrets are never copied into staging;
- secrets never live in committed client PHP config, page content, rendered HTML, or browser JavaScript;
- setup validation should be extended at the same time to detect the environment mismatches that the concrete integration contract makes detectable.

Do not add speculative environment variable names to documentation before the executable integration exists.

## Promotion rule

There are three different kinds of state and they move differently.

### Source-controlled site state

Client source-controlled state may be promoted through the normal repository/deployment workflow, including:

```text
config/client.php
config/features.php
config/features/*.php
config/pages/*.php
config/site.php
resources/views/**
resources/images/raw/**
resources/migration/**
```

### Database-backed editorial state

Reviewed database-backed editorial content is not promoted by copying the staging database.

Enabled editorial Features contribute only their reviewed/promotable rows to the versioned editorial snapshot workflow:

```bash
php artisan editorial:export
php artisan editorial:validate /path/to/snapshot.json
php artisan editorial:import /path/to/snapshot.json --force
```

Normal forward production promotion uses a staging-sourced snapshot. Production creates its own rollback snapshot automatically before replacement. Production snapshots may be restored to production. Staging does not accept editorial snapshot imports because it remains the authoring/review database.

See:

```text
docs/operations/editorial-promotion.md
```

### Runtime environment state

Runtime environment state is never part of either source promotion or editorial snapshots.

Do not promote or copy a real staging `.env` into production, and do not promote or copy a real production `.env` into staging.

In particular, future integration destinations, client IDs, signing secrets, tokens, or equivalent credentials are runtime-specific deployment state. They must never be treated as content/configuration or editorial state that moves from staging to production.

A deployment may use the same committed `.env.example` template in both environments. Each environment must populate its own real `.env` values independently.

## Current setup-validation boundary

`php artisan setup:validate` currently has enough information to catch structural problems such as:

- missing selected-client environment files;
- unsupported client environment keys;
- root/client environment ownership conflicts;
- missing required client-owned variables;
- malformed `APP_URL`/database identity values;
- other implemented site/Feature/SEO contracts.

It does **not** currently have a Core integration destination or Core credential contract to compare, because Engage SEO Sites does not yet implement that integration seam.

Therefore an SEO-to-Core staging/production mismatch check is intentionally a later hardening step that must ship with the real integration contract rather than being guessed now.

## SEO staging behavior

Staging should retain the normal non-production indexing protection.

The Engage SEO indexing policy already requires `APP_ENV=production` before the site can become indexable, even when a client accidentally enables its `site.seo.indexing_enabled` switch.

Production launch still requires the explicit client indexing switch plus the normal setup/migration/readiness checks.

## Deployment checklist

For staging:

1. confirm root `APP_ENV=staging`;
2. confirm the selected `CLIENT_KEY`;
3. create a staging-specific `clients/[CLIENT_KEY]/.env`;
4. confirm `APP_URL` uses the staging public site;
5. confirm database/cache/Redis namespaces are staging-specific;
6. when Core integration exists, confirm every Core destination and credential belongs to staging;
7. run `php artisan optimize:clear` and rebuild config cache as appropriate;
8. run `php artisan setup:validate`;
9. confirm staging remains non-indexable.

For production:

1. confirm root `APP_ENV=production`;
2. confirm the selected `CLIENT_KEY`;
3. create a production-specific `clients/[CLIENT_KEY]/.env` independently of staging;
4. confirm `APP_URL` uses the production public site;
5. confirm database/cache/Redis namespaces are production-specific;
6. when Core integration exists, confirm every Core destination and credential belongs to production;
7. run the old-platform migration audit when applicable;
8. run `php artisan optimize:clear` and rebuild config cache as appropriate;
9. run `php artisan setup:validate`;
10. enable production indexing only when the site is ready for cutover.