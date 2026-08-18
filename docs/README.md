# Engage SEO Documentation

Engage SEO is a reusable Laravel platform for client-facing business and SEO websites.

The documentation is intentionally organized around durable platform boundaries rather than individual client implementations.

## Structure

```text
docs/
    architecture/
        platform-boundaries.md
        public-rendering.md
        site-shell-theme.md

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

The platform foundation establishes:

- one selected client through `CLIENT_KEY`;
- client-specific environment and PHP configuration under `clients/{CLIENT_KEY}`;
- platform Git isolation for client repositories;
- lightweight reusable Features;
- lightweight Verticals that compose shared Features rather than duplicate them;
- safe client scaffold/reset/refresh scripts;
- setup validation;
- config-owned static public page resolution;
- normalized metadata and section contracts;
- explicit public page/section client view seams;
- normalized public site identity, shell, navigation, footer, and semantic theme-token contracts.

Richer reusable section components, sitemap/schema/redirect infrastructure, media pipelines, CMS Features, and Engage Core integrations remain later slices.