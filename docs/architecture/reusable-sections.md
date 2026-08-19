# Reusable Public Sections

## Purpose

Engage SEO composes config-owned public pages from a small platform library of reusable sections.

Sections are intentionally generic. They describe durable presentation/content patterns such as a hero, card grid, process steps, or CTA. They do not encode mortgage terminology, client claims, client colors, or client-specific conversion destinations.

The selected client owns business content in:

```text
clients/{CLIENT_KEY}/config/pages/*.php
```

The platform owns reusable section behavior and default markup under:

```text
resources/views/sections/
```

## Registry

Reusable sections are registered in:

```text
config/sections.php
```

Each component declares:

```text
platform view
supported layouts
```

The platform also registers shared semantic themes.

Current themes:

```text
default
surface
inverse
accent
```

Current reusable components:

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

`setup:validate` rejects unknown components and unsupported theme/layout combinations.

## Normalized page envelope

A configured section keeps the existing normalized envelope:

```php
[
    'id' => 'optional-section-id',
    'component' => 'hero',
    'theme' => 'inverse',
    'layout' => 'media-right',
    'props' => [
        // Component-owned content.
    ],
    'overrides' => [
        // Reserved for an explicit client section override.
    ],
]
```

`id`, `theme`, and `layout` may be omitted.

Omitted theme/layout values use the section's default presentation.

Platform section views do not interpret `overrides` as arbitrary CSS or Tailwind classes. A client that truly needs specialized markup may override a registered section through the documented `client::sections.*` view seam.

## Shared themes

Section views emit semantic `data-section-theme` values.

The default platform CSS maps those values to the selected site's semantic theme tokens.

`inverse` uses the dedicated contrasting palette:

```text
colors.inverse_background
colors.inverse_surface
colors.inverse_text
colors.inverse_muted
colors.inverse_border
```

This supports common patterns such as a dark hero followed by light educational content without making dark/light color values part of page configuration.

`accent` uses the selected client's primary and primary-contrast tokens.

## Actions

Sections that expose calls to action use the shared `<x-section-actions>` component.

An action is:

```php
[
    'label' => '...',
    'url' => '/destination',
    'variant' => 'primary',
]
```

Supported variants:

```text
primary
secondary
text
```

Supported destinations:

```text
/site-path
#fragment
https://...
http://...
mailto:...
tel:...
```

Unsafe URL schemes are rejected.

## Static images

Hero and split-content media use the existing manifest-driven responsive image foundation.

Image configuration is:

```php
'media' => [
    'asset' => 'heroes/example',
    'alt' => 'Meaningful alternative text',
    'sizes' => '(min-width: 64rem) 50vw, 100vw',
    'loading' => 'eager',
    'fetchpriority' => 'high',
],
```

`asset` resolves through the generated media manifest.

`alt` must be explicitly provided as a string. Use `alt => ''` only when an image is intentionally decorative.

`sizes`, `loading`, and `fetchpriority` are optional and fall back to the responsive-image component defaults.

## Embedded media

`media-embed` uses `<x-embed-frame>` rather than allowing arbitrary iframe/script markup in page configuration.

The embed contract is:

```php
'embed' => [
    'src' => 'https://provider.example/embed/...',
    'title' => 'Accessible media title',
    'loading' => 'lazy',
],
```

Only absolute HTTP/HTTPS embed URLs are accepted.

External script-based widgets are not part of this section. A rates widget, calculator, or another vendor script should establish a separate trusted integration seam rather than placing raw scripts in page config.

## Component contracts

### `content`

Useful for authority, local SEO, explanation, or supporting copy.

Common props:

```text
eyebrow
title
content      string or list of paragraphs
bullets      list of strings
actions      action list
```

Layouts:

```text
default
narrow
wide
```

### `hero`

Owns the page-level H1.

Common props:

```text
eyebrow
title
subtitle
content
actions
proof
media
```

Layouts:

```text
default
media-right
media-left
centered
```

### `content-split`

Pairs structured copy with an optional responsive image.

Common props:

```text
eyebrow
title
content
bullets
actions
media
```

Layouts:

```text
default
media-right
media-left
```

### `card-grid`

Reusable for benefits, loan/service choices, comparison points, and other skimmable grouped content.

Each item may contain:

```text
eyebrow
title
description
bullets
action
```

Layouts:

```text
default
two
three
four
balanced-five
```

`card-grid` replaces the need for separate generic `feature-grid` and `info-grid` platform concepts.

### `steps`

Ordered process content.

Each item may contain:

```text
step
title
content
bullets
```

Layouts:

```text
default
two
three
```

### `cta`

Focused conversion section.

Common props:

```text
eyebrow
title
content
actions
```

Layouts:

```text
default
centered
split
```

### `media-embed`

Structured explanatory copy plus a trusted iframe embed.

Common props:

```text
eyebrow
title
content
actions
embed
```

Layouts:

```text
default
wide
split
```

### `stats`

Semantic `<dl>` proof/authority values.

Each item contains:

```text
value
label
```

Layouts:

```text
default
three
four
```

### `testimonials`

Reusable social-proof/review cards.

Each item may contain:

```text
quote
name
context
source
rating
```

`rating`, when provided, is an integer from 1 through 5.

This is a presentation contract, not a live Google Reviews integration.

Layouts:

```text
default
two
three
```

### `faq`

Native `<details>` disclosure items requiring no JavaScript.

Each item contains:

```text
question
answer       string or list of paragraphs
```

Layouts:

```text
default
narrow
two-column
```

The section does not automatically claim FAQ structured data. Structured data should be added only when the page's actual content and SEO policy warrant it.

## Client override seam

A selected client may override a registered section at:

```text
clients/{CLIENT_KEY}/resources/views/sections/{component}.blade.php
```

The platform section remains mandatory and must exist even when a client override is present.

Use an override only when the shared contract cannot express a legitimate client presentation requirement.

Do not create client copies merely to change copy, colors, basic section theme, layout, media, or actions.

## Security and content boundary

Shared platform sections:

- escape configured business copy by default;
- do not render arbitrary client HTML;
- validate shared CTA URLs;
- require explicit image alt text;
- validate iframe source schemes;
- do not accept arbitrary Tailwind/CSS class strings from page configuration.

Trusted script widgets remain outside this foundation.

## Testing boundary

Reusable-section tests cover:

- registry/view availability;
- page composition through the normalized section envelope;
- supported theme/layout validation;
- safe action URLs;
- explicit image-alt requirements;
- embed URL safety;
- explicit client section override selection.

Tests do not freeze client-facing wording, client styling, Tailwind classes, or visual layout details.