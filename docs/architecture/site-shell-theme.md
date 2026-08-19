# Site Shell and Theme Foundation

## Purpose

Engage SEO separates public-site structure and presentation configuration from client business content.

The platform owns a normalized shell contract for:

```text
site identity
brand asset references
reusable business/contact identity
utility/compliance bar
header and navigation
conversion-aware structured footer
semantic shell/section themes
semantic theme tokens
```

The selected client supplies configuration through:

```text
clients/{CLIENT_KEY}/config/site.php
```

Client configuration describes data and semantic presentation choices. It does not store large Tailwind class strings or duplicate platform Blade structure.

## Runtime presentation contract

`SitePresentationResolver` normalizes `config('site')` into one `site` array:

```text
site
    name
    brand
        logo
        logo_alt
    business
        phone
        email
        address
        social_links
    shell
        utility_bar
            enabled
            theme
            items
        header
            enabled
            theme
        navigation
            enabled
            items
            primary_cta
        footer
            enabled
            theme
            intro
            groups
            cta
            legal
    theme
        colors
        typography
        layout
        radius
        css_variables
```

`PublicPageController` passes this contract to the public page view alongside the normalized `page` contract.

## Site identity and brand

`site.name` is optional. The effective site name resolves in this order:

```text
site.name
client.name
app.name
```

The brand contract supports:

```php
'brand' => [
    'logo' => '/images/brand/logo.svg',
    'logo_alt' => '...',
],
```

`logo` may be an absolute site path or an absolute HTTP/HTTPS URL. When `logo_alt` is blank or null, the effective site name is used.

## Business identity

Reusable business/contact identity lives under `site.business` rather than being embedded directly in footer markup.

All fields are optional:

```php
'business' => [
    'phone' => [
        'label' => '...',
        'value' => '...',
        'url' => 'tel:+15555555555',
    ],
    'email' => [
        'label' => '...',
        'value' => '...',
        'url' => 'mailto:hello@example.com',
    ],
    'address' => [
        'lines' => [
            '...',
            '...',
        ],
        'url' => 'https://maps.example.com/...',
        'new_tab' => true,
    ],
    'social_links' => [
        [
            'label' => '...',
            'url' => 'https://social.example/...',
            'new_tab' => true,
        ],
    ],
],
```

Phone links must use `tel:`. Email links must use `mailto:`. Social links are limited to HTTP/HTTPS.

Business data is intentionally generic. Mortgage license numbers, contractor licenses, office disclosures, or similar industry-specific text do not belong in this contract merely because one client needs them. Those belong in the configurable utility/footer disclosure surfaces.

## Utility/compliance bar

The optional utility bar appears inside the public header above the primary header row.

```php
'utility_bar' => [
    'enabled' => true,
    'theme' => 'inverse',
    'items' => [
        [
            'text' => '...',
        ],
        [
            'label' => '...',
            'url' => 'tel:+15555555555',
        ],
    ],
],
```

An item is either plain text or a link, never both. This makes the bar suitable for licensing/disclosure text, contact shortcuts, or similarly compact business information without creating a mortgage-specific concept.

## Navigation

Primary navigation remains under `site.shell.navigation`.

Link items support:

```php
[
    'label' => '...',
    'url' => '/about',
    'new_tab' => false,
]
```

Grouped items continue to use `children` instead of `url`.

Allowed navigation URL types are absolute site paths, HTTP/HTTPS, `mailto:`, and `tel:`.

`new_tab` is always explicit. When true, the platform renders `target="_blank"` with `rel="noopener noreferrer"`. The platform does not automatically force external links into new tabs.

Internal links receive an `active` flag by comparing their path to the current request path. Absolute HTTP/HTTPS links are also normalized with an `external` flag by comparing their host to `APP_URL`.

## Shell themes

The utility bar, header, and footer support semantic shell themes:

```text
default
inverse
```

These map to the existing normal/inverse site color tokens. Client configuration selects the semantic theme name, not CSS classes or raw CSS variable names.

## Structured footer

The footer is no longer a single flat navigation list. It supports business-site needs without encoding client-specific wording:

```php
'footer' => [
    'enabled' => true,
    'theme' => 'inverse',
    'intro' => '...',
    'groups' => [
        [
            'label' => '...',
            'items' => [
                [
                    'label' => '...',
                    'url' => '/...',
                ],
            ],
        ],
    ],
    'cta' => [
        'title' => '...',
        'description' => '...',
        'actions' => [
            [
                'label' => '...',
                'url' => '/...',
            ],
        ],
    ],
    'legal' => [
        'lines' => [
            '...',
        ],
        'links' => [
            [
                'label' => '...',
                'url' => '/privacy',
            ],
        ],
    ],
],
```

Footer groups require at least one link. A configured footer CTA requires a title and at least one action. Legal/disclosure lines remain plain escaped text; arbitrary HTML is not accepted.

The platform footer also renders configured business contact/address/social data using semantic `address` and navigation landmarks where appropriate.

## Theme tokens

Theme configuration remains semantic:

```text
colors
typography
layout
radius
```

Current color tokens include the normal palette plus:

```text
colors.inverse_background
colors.inverse_surface
colors.inverse_text
colors.inverse_muted
colors.inverse_border
```

The resolver maps fixed configuration tokens to fixed CSS custom properties. Client configuration cannot define arbitrary custom-property names or inject CSS declarations through theme values.

## Client overrides

Normal client customization should happen through `config/site.php`.

The explicit client public-page override remains:

```text
clients/{CLIENT_KEY}/resources/views/pages/public.blade.php
```

Reusable section overrides remain:

```text
clients/{CLIENT_KEY}/resources/views/sections/{component}.blade.php
```

The platform does not automatically replace header/footer/layout Blade files from arbitrary client paths. Add a new override seam only when a real requirement cannot be represented cleanly by the normalized shared contract.

## Setup validation

`php artisan setup:validate` validates the effective site presentation configuration through `SitePresentationResolver`.

It rejects malformed contracts including:

- unsupported site/business/shell keys;
- malformed phone/email/address/social data;
- unsafe or unsupported URL schemes;
- non-boolean enablement and `new_tab` values;
- malformed utility-bar items;
- malformed navigation groups;
- unsupported shell themes;
- empty configured footer groups;
- configured footer CTAs without actions;
- malformed legal/disclosure lists;
- malformed or unsafe theme-token values.

## Testing boundary

Site-shell tests cover normalization, active/external link state, safe new-tab behavior, semantic regions, enablement, and malformed contracts.

Tests do not assert client-specific language, client-specific licenses/disclosures, visual Tailwind classes, or exact layout styling.