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
```

The resolved view contract contains:

```text
title
description
canonical
robots
open_graph
twitter
```

`canonical` defaults to the configured page path resolved against `APP_URL`.

`indexable=false` resolves to:

```text
noindex,nofollow
```

Open Graph and Twitter values inherit the normalized page metadata unless they
are explicitly overridden.

The page `title` is treated as the final browser/SEO title. The platform does not
silently append a brand suffix.

Sitemap generation, redirects, JSON-LD/schema.org, and environment-specific
robots policy remain later SEO infrastructure.

## Section dispatch

Platform section keys are registered in:

```text
config/sections.php
```

The first foundation primitive is:

```text
content
```

Unknown section keys or registered keys whose platform view is missing fail as
configuration/runtime errors. Public pages never emit debug banners describing
missing Blade files.

More section components should be added only when real client requirements
establish their reusable contract.

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

## Base public layout

The platform public layout owns:

- document language;
- charset and viewport;
- normalized SEO metadata output;
- Vite asset loading;
- a skip link;
- the primary `<main>` landmark.

Header, navigation, footer, themes, and design-system behavior are not defined by
this foundation. They will be layered onto the stable rendering contract later.

## Testing boundary

Public rendering tests cover:

- path resolution;
- 404 behavior;
- normalized page/meta/section data passed to the view;
- generic client page-view override selection;
- setup-validation behavior.

Tests do not assert client-specific wording, visual classes, or client-specific
page copy.