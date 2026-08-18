# Engage SEO Documentation

Engage SEO is a reusable Laravel platform for client-facing business and SEO websites.

The documentation is intentionally organized around durable platform boundaries rather than individual client implementations.

## Structure

```text
docs/
    architecture/
        platform-boundaries.md

    configuration/
        client-configuration.md

    operations/
        client-lifecycle.md
```

Use:

- `architecture/` for durable ownership and dependency rules;
- `configuration/` for supported configuration shapes and precedence;
- `operations/` for repeatable local, staging, deployment, reset, and client-management procedures.

Client-specific business content does not belong in the platform documentation unless it establishes a reusable platform requirement.

## Current foundation

The first platform foundation establishes:

- one selected client through `CLIENT_KEY`;
- client-specific environment and PHP configuration under `clients/{CLIENT_KEY}`;
- platform Git isolation for client repositories;
- lightweight reusable Features;
- lightweight Verticals that compose shared Features rather than duplicate them;
- safe client scaffold/reset/refresh scripts.

Page rendering, theming, CMS features, SEO metadata, accessibility components, and Engage Core integrations are intentionally deferred to later implementation slices.