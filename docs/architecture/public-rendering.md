# Public Rendering Foundation

## Purpose

Engage SEO public pages use a configuration-owned rendering contract.

Static SEO/business pages are not database records by default. A selected client
owns page definitions under:

```text
clients/{CLIENT_KEY}/config/pages/*.php
```

Database-backed Features such as Blog may register their own explicit routes and
runtime later.

## Routing

The platform owns:

```text
GET /
fallback public page route
```

The explicit root route and Laravel fallback route both resolve through the same
public page controller.

The fallback is intentional. Explicit Feature routes remain separate and should
take precedence over generic configured pages.

A configured page must declare an absolute path:

```php
return [
    'path' => '/about',
    'meta' => [],
    'sections' => [],
];
```

Page configuration keys are internal identifiers. The public URL comes from
`path`.

Duplicate configured paths are setup-validation errors.

## Normalized page contract

The renderer passes one `page` array to the public page view:

```text
page
    key
    path
    meta
    sections
```

Every normalized section contains:

```text
id
component
theme
layout
overrides
props
```

`id`, `theme`, and `layout` may be null.

`overrides` and `props` are always arrays.

The homepage follows this same contract. It is not a separate one-off rendering
system.

## Site presentation contract

The public page controller also passes one normalized `site` array.

It contains the selected client's effective:

```text
site name
brand asset references
header/navigation/footer configuration
semantic theme tokens
CSS custom properties
```

The platform public page view therefore receives:

```text
$page
$site
```

See:

```text
docs/architecture/site-shell-theme.md
```

for the complete shell/theme contract.

## Metadata contract

Page metadata may define:

```text
title
description
canonical
indexable
image
open_graph
twitter
structured_data
```

The resolved view contract contains:

```text
title
description
canonical
indexable
robots
open_graph
twitter
structured_data
```

`canonical` defaults to the configured page path resolved against `APP_URL`.

The page-level/default `indexable` value is a request, not a bypass of site
safety policy. Effective indexing also requires:

```text
APP_ENV=production
selected client
site.seo.indexing_enabled=true
```

A blocked page resolves to:

```text
indexable=false
robots=noindex,nofollow
```

Open Graph and Twitter values inherit the normalized page metadata unless they
are explicitly overridden.

`structured_data` is a list of associative-array JSON-LD nodes. Registered
Feature contributors may append additional nodes before the page renders.

The page `title` is treated as the final browser/SEO title. The platform does not
silently append a brand suffix.

See:

```text
docs/architecture/seo-infrastructure.md
```

for robots, sitemap, redirects, indexing policy, and Feature SEO contribution
contracts.

## Section dispatch

Platform section keys are registered in:

```text
config/sections.php
```

The reusable platform library currently includes:

```text
content
hero
content-split
card-grid
steps
cta
media-embed
stats
testimonials
faq
```

Each registered component declares its platform view and supported layout names.
Shared semantic section themes are registered separately.

`PageRepository` validates configured theme/layout combinations before launch
through `setup:validate` and enforces the same contract while normalizing pages
at runtime.

Unknown section keys, unsupported theme/layout combinations, malformed registry
definitions, or registered keys whose platform view is missing fail as
configuration/runtime errors. Public pages never emit debug banners describing
missing Blade files.

The `overrides` field remains part of the normalized section envelope for
explicit client section overrides. Shared platform sections do not interpret it
as arbitrary CSS/Tailwind configuration.

See:

```text
docs/architecture/reusable-sections.md
```

for the reusable section contracts and presentation boundary.

## Client view seam

When a client is selected, Engage SEO registers this explicit Blade namespace:

```text
client::
```

The selected client view root is:

```text
clients/{CLIENT_KEY}/resources/views
```

The public renderer currently permits these automatic presentation overrides:

```text
resources/views/pages/public.blade.php
resources/views/sections/{component}.blade.php
```

They resolve as:

```text
client::pages.public
client::sections.{component}
```

The namespace does not mean arbitrary client files replace platform views.
Platform code must explicitly resolve a client-namespaced view for an override
to take effect.

A client override of `pages/public.blade.php` receives both normalized contracts:

```text
$page
$site
```

## Base public layout

The platform public layout owns:

- document language;
- charset and viewport;
- normalized SEO metadata output;
- normalized theme CSS custom properties;
- Vite asset loading;
- a skip link;
- the primary `<main>` landmark;
- generic semantic header/navigation/footer rendering when enabled.

The selected client controls shell contents and presentation tokens through
`config/site.php`.

The foundation does not require a client to duplicate the platform layout merely
to change branding, navigation, colors, typography, or shell visibility.

## Testing boundary

Public rendering tests cover:

- path resolution;
- 404 behavior;
- normalized page/meta/section data passed to the view;
- normalized site presentation data passed to the view;
- generic client page-view override selection;
- setup-validation behavior.

Tests do not assert client-specific wording, visual classes, or client-specific
page copy.