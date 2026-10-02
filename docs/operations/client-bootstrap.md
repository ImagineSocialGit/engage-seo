# Selected-Client Bootstrap

## Purpose

Engage SEO selected-client bootstrap initializes source-controlled database state that must exist when a client is first brought up in a fresh environment.

Typical use cases include:

```text
historical Blog content imported during a site migration
initial testimonial/catalog rows for a future database-backed Feature
other durable client-owned initial state with a registered platform contributor
```

Bootstrap is not a CMS, a production editing workflow, or a replacement for database migrations.

## Runtime contract

Run bootstrap only after migrations:

```bash
php artisan migrate --force
php artisan client:bootstrap
```

The command works in local, staging, and production environments.

Each enabled capability may register a `ClientBootstrapContributor`. A selected client provides source-controlled data at:

```text
client/{CLIENT_KEY}/bootstrap/{contributor-key}.php
```

Each source file returns:

```php
<?php

return [
    'version' => 1,
    'data' => [
        // Contributor-owned initial-state payload.
    ],
];
```

The platform, not the client file, owns how that payload is validated and written.

## One-time / immutable behavior

Bootstrap is deliberately initial-state only.

For each selected client and contributor, Engage SEO records:

```text
client key
contributor key
source version
canonical payload checksum
applied timestamp
```

An identical later run is skipped.

If the source version or payload changes after that contributor has already been applied to the same database, `client:bootstrap` fails rather than silently replacing database state.

Use the appropriate Feature editorial/promotion workflow or an explicit reviewed data migration for later changes.

This protects local/staging/production environments from a source bootstrap file unexpectedly overwriting content that has been reviewed or edited after initialization.

## Fresh environments

A new environment has no bootstrap record, so it applies the currently deployed source version.

That means the same committed client repository can initialize:

```text
local
staging
production
```

without copying a database between environments.

Deploy/build any static media referenced by bootstrap payloads before running the command.

## Blog contributor

When Blog is enabled, the Blog Feature registers the `blog` bootstrap contributor.

The client source is:

```text
client/{CLIENT_KEY}/bootstrap/blog.php
```

Its `data` value uses the same validated Blog section contract used by editorial promotion:

```text
version
categories
posts
```

The contributor requires the Blog editorial tables to be empty before the first application. It then reuses the existing Blog snapshot validation/application contract, including structured-content and media-asset validation.

Once Blog rows exist, bootstrap will not replace them.

After initial state exists, normal reviewed content movement remains:

```text
staging editorial review
    -> editorial:export
    -> production editorial:validate
    -> production editorial:import --force
```

## Local refresh

`./scripts/dev-refresh-client.sh` performs:

```text
safe local database reset
migrations
client:bootstrap
```

A migrated client with source-controlled historical Blog content therefore returns to its baseline automatically after a deliberate local refresh.

## Failure behavior

The command fails when:

- no valid selected client exists;
- the bootstrap tracking migration has not run;
- a source filename has no registered contributor;
- a source wrapper is malformed;
- contributor validation fails;
- required Feature tables or media assets are unavailable;
- an already-applied contributor no longer matches its recorded version/checksum.

Contributors are applied in one database transaction. A failure prevents partial multi-contributor initialization.

## Deployment order

For a new staging/production environment:

1. deploy matching platform code;
2. deploy the selected client repository;
3. build/deploy client static media;
4. run migrations;
5. run `php artisan client:bootstrap`;
6. run `php artisan setup:validate`;
7. verify public Feature behavior;
8. continue with the normal SEO launch or editorial-promotion workflow.

Bootstrap never moves `.env` state, credentials, databases, or runtime integration configuration between environments.