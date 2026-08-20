# Editorial Promotion

## Purpose

Engage SEO Sites keeps editable editorial state separate from production serving state.

The canonical direction is:

```text
staging editorial review
    -> reviewed editorial snapshot
    -> production validation
    -> production import
```

Do not copy the staging database into production to publish content.

The promotion artifact contains only Feature-contributed editorial content. It does not contain environment variables, database credentials, cache/Redis namespaces, service credentials, or unrelated application tables.

## Current contributors

The Blog / Learning Center Feature is the first editorial promotion contributor.

Only enabled Features may contribute sections to a snapshot. The snapshot must contain exactly the editorial contributor sections enabled on the target. A missing or unexpected section fails validation rather than being silently ignored.

Future database-backed Features can join the same workflow by implementing the platform editorial promotion contributor contract.

## Blog review contract

For Blog:

```text
published_at = null
    -> staging draft; not promoted

published_at != null
    -> reviewed/promotable
```

A future `published_at` is a reviewed scheduled post and is included in the snapshot so production can publish it at the intended time.

Only categories attached to promotable posts are included. Database primary keys are environment-local implementation details and are not promoted; Blog slugs and category relationships are reconstructed from the snapshot.

Production is not a drafting environment. If production contains Blog rows with `published_at=null`, production export/backup refuses to proceed so an accidental production draft cannot be silently discarded by a later replacement import.

## Snapshot envelope

The artifact uses a versioned JSON envelope:

```text
format
version
client_key
source_environment
generated_at
sections
checksum
```

The checksum is SHA-256 over a canonical representation of the document excluding the checksum field itself.

Validation checks:

- supported snapshot format/version;
- structural JSON contract;
- checksum integrity;
- selected-client match;
- source/target environment direction;
- exact enabled contributor section set;
- Feature-specific content structure;
- target Feature tables/migrations;
- target availability of Blog media assets referenced by promoted posts/content.

The snapshot should be treated as an immutable promotion artifact after export. If content changes, export a new artifact rather than hand-editing the JSON and recomputing its checksum.

## Export on staging

After editorial review is complete:

```bash
php artisan editorial:export
```

The default file is written under protected application storage:

```text
storage/app/private/editorial/
```

The command prints:

- selected client;
- source environment;
- included sections;
- snapshot path;
- checksum.

An explicit output path may be supplied:

```bash
php artisan editorial:export /secure/path/client-editorial.json
```

Transfer the artifact to production through the approved deployment/operations channel. Do not place the snapshot under `public/` merely for convenience.

## Validate on production

Deploy the matching platform/client source and static media first, then run migrations:

```bash
php artisan migrate --force
```

Validate the transferred snapshot before mutation:

```bash
php artisan editorial:validate /secure/path/client-editorial.json
```

Production accepts normal forward snapshots from:

```text
source_environment=staging
```

It also accepts:

```text
source_environment=production
```

only so a production rollback backup created by the import workflow can be restored.

Production rejects snapshots sourced from local/testing or another unsupported environment.

## Apply on production

Import always requires an explicit destructive acknowledgement:

```bash
php artisan editorial:import /secure/path/client-editorial.json --force
```

Before applying the incoming snapshot, Engage SEO automatically exports the current production editorial state to:

```text
storage/app/private/editorial/backups/
```

The incoming snapshot is then validated again and applied in a database transaction across all registered editorial contributors.

For Blog, apply replaces only:

```text
blog_categories
blog_posts
blog_category_post
```

It does not wipe or copy unrelated application tables.

## Rollback

If the newly promoted editorial state must be reverted, use the backup path printed by the successful import:

```bash
php artisan editorial:validate /path/to/production-backup.json
php artisan editorial:import /path/to/production-backup.json --force
```

A rollback snapshot was sourced from production, so production accepts it.

Do not import snapshots into staging. Staging remains the forward editorial authoring/review environment and may contain drafts that must not be replaced by a promotion artifact.

## Deployment order

For a normal production promotion:

1. deploy matching platform code;
2. deploy the selected client source-controlled config/views/raw media;
3. build/deploy generated media as required;
4. run `php artisan migrate --force`;
5. run `php artisan setup:validate` as appropriate for the deployment stage;
6. transfer the staging editorial snapshot to protected production storage;
7. run `php artisan editorial:validate ...`;
8. run `php artisan editorial:import ... --force`;
9. verify public Blog index/article/category behavior;
10. complete the normal SEO launch/indexing checks.

This order ensures production has the schema and source-controlled dependencies required by the snapshot before editorial rows are replaced.

## Environment and credential boundary

Editorial promotion does not replace environment pairing.

Continue to enforce:

```text
staging Engage SEO Sites -> staging Engage Core only
production Engage SEO Sites -> production Engage Core only
```

A snapshot never promotes:

```text
.env files
APP_URL
DB credentials
CACHE_PREFIX
REDIS_PREFIX
SESSION_DOMAIN
future Core API destinations
client IDs
signing secrets
tokens
```

Those values remain independently configured runtime state in each environment.

## No production CMS assumption

These commands establish reviewed state promotion without creating a production editing surface.

A future browser-based editorial UI should be staging-only unless a separately reviewed requirement changes that boundary. Production remains optimized for serving the promoted public state and controlled rollback/import operations.