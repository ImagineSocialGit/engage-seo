# Engage SEO Platform Boundaries

## Product shape

Engage SEO is one reusable Laravel platform that can serve many separately versioned client websites.

The durable layers are:

```text
Platform
    shared runtime, rendering, SEO, accessibility, configuration, tooling

Features
    reusable optional site capabilities

Verticals
    curated defaults and content patterns for an industry/category

Client
    final brand, content, pages, feature selection, overrides, integrations
```

These layers are deliberately smaller and simpler than Engage Core modules.

## Platform

The platform owns reusable infrastructure such as:

- selected-client loading;
- shared page/rendering primitives;
- shared SEO metadata and structured-data seams;
- shared accessibility contracts;
- shared media/image handling;
- shared Feature registration and enablement;
- shared Vertical resolution;
- client lifecycle tooling;
- generic tests and documentation.

The platform must not contain Slam Dunk-specific, mortgage-specific, construction-specific, pet-specific, or other client business content.

## Features

Features are optional reusable capabilities.

Examples may eventually include:

```text
blog
forms
services
testimonials
locations
reviews
```

A feature may own only what it genuinely requires:

```text
routes
controllers
models
migrations
services
views
configuration
admin/CMS surfaces
```

Do not create empty architectural layers merely for symmetry.

A generic capability remains generic even when different verticals present it differently.

Prefer:

```text
Services feature
    configured by Construction
    configured differently by Pets
```

Avoid:

```text
ConstructionServices feature
PetServices feature
```

unless the runtime behavior is genuinely different enough to justify separate capabilities.

## Verticals

Verticals are composition/configuration layers above Features.

The current executable Vertical contract is intentionally limited to registered identity plus default Feature composition. Mortgage and Pets are the first built-in Verticals, and both currently compose the generic Services, Locations, and Blog Features.

A client may select no Vertical. A client may also add or disable Features independently of Vertical defaults, with the client disable list remaining final Feature-enablement authority.

Future terminology, page/content presets, fields, section presets, and information-architecture defaults should be added only after repeated client implementations establish a reusable contract. Client-specific business content does not belong in a Vertical.

See:

```text
docs/architecture/verticals.md
```

## Clients

Client repositories live under:

```text
clients/{CLIENT_KEY}/
```

The platform repository ignores client directories so each client may be its own Git repository.

A client repository may own:

- `config/client.php`;
- `config/features.php`;
- additional supported client configuration;
- client views/overrides when a supported seam exists;
- client images/assets;
- client-specific `.env.example`;
- deployment-specific `.env` outside Git;
- client documentation.

Client code/config must use documented platform seams rather than reaching into another Feature's private implementation.

## Engage Core boundary

Engage SEO must work without Engage Core.

When Engage Core is connected, Engage SEO should call explicit Core integration seams instead of duplicating CRM behavior.

For example, a future Forms Feature may own:

```text
public form presentation
site-side validation/presentation
server-to-server request assembly
friendly success/error rendering
```

When Core-backed, Engage Core may remain authoritative for:

```text
Contact creation/update
consent
FormSubmission persistence
campaigns
automation
messaging
scheduling
other CRM lifecycle behavior
```

The SEO platform must not create parallel CRM state merely because a public site needs to display or submit a form.

## Production admin boundary

Public production sites should serve published site content without exposing general web-admin authentication by default.

If a Feature needs administrative editing, its editing surface should follow the platform's staging/admin deployment policy rather than assuming that production requires an exposed CMS login.

## Testing boundary

Automated tests should verify:

- runtime behavior;
- configuration contracts;
- persistence;
- validation;
- semantic/accessibility behavior where functional;
- integration boundaries.

Do not freeze Tailwind class strings, exact visual layout, or arbitrary client prose in generic platform tests.