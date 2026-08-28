# Engage SEO Documentation

Engage SEO is a reusable Laravel platform for client-facing business and SEO websites.

The documentation is intentionally organized around durable platform boundaries rather than individual client implementations.

## Structure

```text
docs/
    architecture/
        media-images.md
        old-platform-migration.md
        platform-boundaries.md
        public-rendering.md
        reusable-sections.md
        seo-infrastructure.md
        site-shell-theme.md
        verticals.md

    features/
        blog.md
        locations.md
        services.md

    configuration/
        client-configuration.md

    operations/
        client-lifecycle.md
        editorial-promotion.md
        environment-pairing.md
        testing.md
```

Use:

- `architecture/` for durable ownership and dependency rules;
- `features/` for reusable Feature ownership and public runtime contracts;
- `configuration/` for supported configuration shapes and precedence;
- `operations/` for repeatable local, staging, deployment, reset, testing, and client-management procedures.

Client-specific business content does not belong in the platform documentation unless it establishes a reusable platform requirement.

## Current foundation

The platform foundation establishes:

- one selected client through `CLIENT_KEY`;
- client-specific environment and PHP configuration under `client/{CLIENT_KEY}`;
- platform Git isolation for client repositories;
- lightweight reusable Features;
- lightweight Mortgage and Pets Verticals that compose shared Services, Locations, and Blog Features while preserving client override authority;
- safe client scaffold/reset/refresh scripts;
- setup validation;
- config-owned static public page resolution;
- normalized metadata and section contracts;
- explicit public page/section client view seams;
- normalized public site identity, shell, navigation, footer, and semantic theme-token contracts;
- production-gated indexing, dynamic robots/sitemap output, redirects, page JSON-LD, and Feature SEO contribution seams;
- old-platform SEO migration inventory and cutover coverage auditing;
- manifest-driven static client media with responsive AVIF/WebP generation and provider-neutral public URL resolution;
- a reusable public section library for common content, conversion, process, media, proof, review, and FAQ patterns;
- reusable configuration-backed Services and Locations catalogs using the shared Feature/setup-validation contribution seams;
- a database-backed Blog / Learning Center public runtime with structured editorial content, categories, publication state, sitemap contribution, and Article structured data;
- versioned, checksummed editorial snapshots for reviewed staging-to-production content promotion with target validation and automatic production rollback backups;
- MySQL-based automated database testing through a dedicated guarded `.env.testing` runtime;
- explicit Engage SEO Sites staging/production environment-pairing and runtime-credential promotion rules.

A staging-only browser editorial UI, additional Features, richer evidence-driven Vertical presets, and the executable Engage Core integration contract remain later slices.