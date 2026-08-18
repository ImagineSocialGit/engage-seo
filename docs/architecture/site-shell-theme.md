# Site Shell and Theme Foundation

## Purpose

Engage SEO separates public-site structure and presentation configuration from client business content.

The platform owns the shell contract:

```text
site identity
brand asset references
header visibility
navigation structure
footer visibility
semantic theme tokens
```

The selected client supplies configuration through:

```text
clients/{CLIENT_KEY}/config/site.php
```

The shell is intentionally smaller than the legacy Slam Dunk theme system. Client configuration describes semantic values and navigation data. It does not store large Tailwind class strings or duplicate platform Blade structure.

## Runtime presentation contract

`SitePresentationResolver` normalizes `config('site')` into one `site` array:

```text
site
    name
    brand
        logo
        logo_alt
    shell
        header
            enabled
        navigation
            enabled
            items
            primary_cta
        footer
            enabled
            items
    theme
        colors
        typography
        layout
        radius
        css_variables
```

`PublicPageController` passes this contract to the public page view alongside the normalized `page` contract.

Client page-view overrides therefore receive both:

```text
$page
$site
```

## Site identity and brand

`site.name` is optional.

The effective public site name resolves in this order:

```text
site.name
client.name
app.name
```

The brand contract currently supports:

```php
'brand' => [
    'logo' => '/images/brand/logo.svg',
    'logo_alt' => '...',
],
```

`logo` may be:

- an absolute site path beginning with `/`; or
- an absolute `http` or `https` URL.

When `logo_alt` is blank or null, the effective site name is used.

Favicon and richer brand-asset metadata remain part of later SEO/media infrastructure rather than this shell foundation.

## Navigation

Primary navigation configuration lives under:

```text
site.shell.navigation
```

A link item has this shape:

```php
[
    'label' => '...',
    'url' => '/about',
]
```

A grouped item has this shape:

```php
[
    'label' => '...',
    'children' => [
        [
            'label' => '...',
            'url' => '/service-one',
        ],
    ],
]
```

An item uses either `url` or `children`, not both.

Navigation URLs may be:

```text
/site-path
https://external.example
http://external.example
mailto:...
tel:...
```

Other URL schemes are rejected.

The optional primary CTA uses the same link contract:

```php
'primary_cta' => [
    'label' => '...',
    'url' => '/contact',
],
```

Internal navigation links are normalized with an `active` flag by comparing their URL path to the current public request path. Group items are active when any descendant is active.

The platform header renders desktop and native `<details>`-based mobile navigation without requiring JavaScript.

## Footer

Footer visibility and navigation are configured separately:

```php
'footer' => [
    'enabled' => true,
    'items' => [
        // Same link/group contract as primary navigation.
    ],
],
```

The foundation does not invent client-specific footer copy, contact disclosures, social links, legal language, or business data. Those should be added only when real requirements establish a reusable contract.

## Theme tokens

Theme configuration is semantic:

```text
colors
typography
layout
radius
```

The current required tokens are:

```text
colors.background
colors.surface
colors.text
colors.muted
colors.primary
colors.primary_contrast
colors.border
colors.focus

typography.body_font_family
typography.heading_font_family

layout.content_max_width

radius.control
radius.surface
```

The resolver maps these fixed tokens to fixed CSS custom properties such as:

```text
--site-color-background
--site-color-primary
--site-font-body
--site-content-max-width
```

Client configuration does not choose arbitrary CSS variable names.

Theme token values must be non-blank strings and may not contain characters that could break out of the CSS declaration.

## Client overrides

Normal client customization should happen through `config/site.php`.

The existing explicit client page-view override remains available:

```text
clients/{CLIENT_KEY}/resources/views/pages/public.blade.php
```

This foundation does not automatically replace platform header/footer/navigation Blade files from arbitrary client paths.

That is deliberate. A new presentation override seam should be added only when a real client requirement cannot be represented cleanly by the shared site contract.

## Setup validation

`php artisan setup:validate` validates the effective site presentation configuration.

It rejects malformed contracts including:

- invalid site/brand value types;
- unsupported shell/theme keys;
- non-boolean enablement flags;
- malformed navigation lists;
- navigation items without a URL or children;
- navigation items containing both a URL and children;
- unsupported URL schemes;
- malformed or missing theme token groups;
- unsupported theme token names;
- unsafe/blank theme token values.

The client scaffold also includes:

```text
config/site.php
```

so every new client begins with the same explicit shell ownership.

## Testing boundary

Site-shell tests cover:

- normalization and active-state behavior;
- normalized site data passed to public views;
- enable/disable behavior for semantic shell regions;
- validation failures for malformed configuration.

Tests do not assert client-specific wording, visual Tailwind classes, or exact client-facing copy.