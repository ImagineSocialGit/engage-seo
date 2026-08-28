# Locations Feature

## Purpose

The `locations` Feature provides one reusable, client-owned catalog for physical locations, service areas, cities, regions, or other geographic entries that a business needs to present repeatedly across its public site.

It is intentionally industry-neutral. Mortgage service areas, dog-training facilities, construction service regions, professional-service offices, and similar concepts use the same platform contract.

The Feature does not decide the client's public URL structure or create location-detail routes automatically.

## Enablement

The platform registers `locations` as an optional Feature.

A client may enable it in:

```text
client/[CLIENT_KEY]/config/features.php
```

Example:

```php
<?php

return [
    'enabled' => [
        'locations',
    ],

    'disabled' => [
    ],
];
```

The reusable `locations` section exists only while the Feature provider is enabled.

## Client catalog configuration

Client-owned catalog data belongs in:

```text
client/[CLIENT_KEY]/config/features/locations.php
```

Platform defaults live in:

```text
config/features/locations.php
```

The foundation shape is:

```php
<?php

return [
    'groups' => [
        'region-key' => [
            'title' => '...',
            'intro' => null,
        ],
    ],

    'items' => [
        'location-key' => [
            'title' => '...',
            'summary' => null,
            'group' => 'region-key',
            'address' => [
                // Optional physical/mailing address lines.
            ],
            'image' => null,
            'facts' => [
            ],
            'links' => [
            ],
        ],
    ],
];
```

Group and item keys are stable machine keys. They use lowercase letters, numbers, hyphens, and underscores.

Client-facing names, descriptions, address lines, facts, images, and links remain client-owned data.

## Groups

Groups are optional.

They are useful for structures such as:

```text
states
regions
markets
service territories
facility categories
```

A group supports:

```text
title
intro
```

If any groups are configured, every location item must reference a valid group. Empty configured groups fail setup validation.

## Location items

Each item supports:

```text
title       required
summary     optional
group       optional unless groups exist
address     optional null/list of physical/mailing address lines
image       optional responsive-media reference
facts       optional label/value list
links       optional safe link list
```

The catalog does not require a physical address. A service-area or city entry may omit `address` entirely and use its title, summary, facts, and links instead.

Use `address` only when the lines genuinely represent an address. The public section renders configured address lines with semantic `<address>` markup.

## Facts

Facts are generic label/value pairs:

```php
'facts' => [
    [
        'label' => '...',
        'value' => '...',
    ],
],
```

The platform does not assign business meaning to a fact. A client may use facts for relevant public details such as service coverage, hours, appointment availability, or other location-specific information.

## Links

A location may expose zero or more links:

```php
'links' => [
    [
        'label' => '...',
        'url' => '/location-detail',
        'new_tab' => false,
    ],
    [
        'label' => '...',
        'url' => 'https://maps.example.test/...',
        'new_tab' => true,
    ],
],
```

Allowed destinations use the shared public-site URL contract:

```text
absolute site paths
http
https
mailto
tel
```

Unsafe schemes are rejected. `new_tab` is explicit and safe opener isolation is rendered when enabled.

## Images

Location images reuse the platform static-media contract:

```php
'image' => [
    'asset' => 'locations/example',
    'alt' => '...',
    'sizes' => '100vw',
    'loading' => 'lazy',
    'fetchpriority' => 'auto',
],
```

The `alt` value must be explicitly supplied as a string, including `''` when the image is intentionally decorative.

The Feature does not create a second image pipeline.

## Public section

When enabled, the Feature registers:

```text
component: locations
```

Supported layouts:

```text
default
two
three
four
```

A section may render the complete catalog:

```php
[
    'component' => 'locations',
]
```

one group:

```php
[
    'component' => 'locations',
    'props' => [
        'group' => 'region-key',
    ],
]
```

or an explicit ordered subset:

```php
[
    'component' => 'locations',
    'props' => [
        'items' => [
            'location-b',
            'location-a',
        ],
    ],
]
```

`group` and `items` are mutually exclusive.

The section also accepts optional:

```text
eyebrow
title
intro
```

Unknown props and unknown location/group selections fail setup validation.

## Public page and SEO ownership

The Locations Feature does not automatically create:

```text
/locations
/locations/{slug}
state pages
city pages
facility pages
```

Clients create the SEO pages they actually need under:

```text
client/[CLIENT_KEY]/config/pages/*.php
```

Those normal configured pages remain authoritative for:

```text
public path
canonical URL
indexability
page metadata
sitemap participation
old-platform migration destinations
```

A location catalog link may point at any appropriate configured location page.

This keeps one public-page authority instead of creating a second routing/SEO system inside the Feature.

## Setup validation

When `locations` is enabled, `setup:validate` includes the Feature's validation contributor.

Validation covers:

- catalog configuration shape;
- stable group and location keys;
- required location titles;
- group references;
- empty configured groups;
- address-line lists;
- fact label/value contracts;
- responsive-media reference shape;
- safe location links;
- supported Locations-section props;
- valid group/item section selections;
- an enabled Feature with no location items.

The validator does not verify client-specific geographic claims, business coverage, map-provider data, or search-engine rankings.

## Client view overrides

The normal registered-section override seam applies:

```text
client/[CLIENT_KEY]/resources/views/sections/locations.blade.php
```

Clients should override presentation only when necessary. The catalog remains the shared data authority.

## Deliberately deferred

This foundation does not add:

- database persistence;
- a location CMS;
- automatic location-detail routes;
- geocoding;
- map-provider APIs;
- latitude/longitude management;
- radius/service-boundary geometry;
- automatic LocalBusiness/Place schema;
- location-specific contact/CRM workflows;
- generated city/state copy.

Those should be added only when real client requirements establish a reusable contract that cannot be expressed cleanly through the current catalog, public-page system, and existing SEO extension seams.