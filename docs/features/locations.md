# Locations Feature

## Purpose

The `locations` Feature provides reusable geographic presentation and targeting contracts for public Engage SEO sites.

It remains industry-neutral. Mortgage service markets, dog-training facilities, construction service regions, professional-service offices, and similar concepts can use the same Feature without putting client or Vertical copy into the platform.

The Feature now has two related layers:

```text
catalog
geographic targets
```

They solve different problems.

The **catalog** is presentation-oriented. It can render reusable cards for offices, facilities, service areas, or other geographic entries.

The **geographic target network** is structure-oriented. It describes deliberate state/city/region/market relationships that may point to ordinary configured public pages and can be reused for location hubs, state-to-city internal linking, related-market navigation, and later SEO/schema extensions.

Neither layer creates public pages or routes automatically.

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

A Vertical may also enable the Feature by default. The client's final Feature selection remains authoritative.

When enabled, the Feature registers:

```text
locations
geographic-targets
```

as reusable public section components.

## Client configuration

Client-owned Locations configuration belongs in:

```text
client/[CLIENT_KEY]/config/features/locations.php
```

Platform defaults live in:

```text
config/features/locations.php
```

The complete top-level shape is:

```php
<?php

return [
    'groups' => [
    ],

    'items' => [
    ],

    'targets' => [
    ],
];
```

The catalog (`groups` / `items`) and geographic target network (`targets`) may coexist.

A client that needs only the geographic target network may leave:

```php
'groups' => [],
'items' => [],
```

empty.

An enabled Locations Feature is invalid only when it has neither catalog items nor geographic targets.

---

# Catalog layer

## Purpose

The catalog remains the reusable presentation contract established by the original Locations foundation.

It is appropriate for:

```text
physical offices
facilities
service areas
service territories
location cards
other repeated geographic presentation data
```

## Groups

Groups are optional.

Example:

```php
'groups' => [
    'region-key' => [
        'title' => '...',
        'intro' => null,
    ],
],
```

Group keys are stable machine keys using lowercase letters, numbers, hyphens, and underscores.

If any groups are configured, every catalog item must reference a valid group.

Empty configured groups fail setup validation.

## Catalog items

Example:

```php
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
```

Each item supports:

```text
title       required
summary     optional
group       optional unless groups exist
address     optional null/list of address lines
image       optional responsive-media reference
facts       optional label/value list
links       optional safe link list
```

The catalog does not require a physical address. A service-area or city entry may omit `address`.

Use `address` only when the configured lines genuinely represent an address. The public section renders catalog addresses with semantic `<address>` markup.

## Catalog facts

Facts remain generic label/value presentation data:

```php
'facts' => [
    [
        'label' => '...',
        'value' => '...',
    ],
],
```

The platform does not assign business meaning to these values.

## Catalog links

Catalog items may expose safe links:

```php
'links' => [
    [
        'label' => '...',
        'url' => '/location-detail',
        'new_tab' => false,
    ],
],
```

Allowed destinations follow the shared public-site URL contract:

```text
absolute site paths
http
https
mailto
tel
```

Unsafe schemes fail validation.

## Catalog images

Catalog images reuse the platform responsive-media contract:

```php
'image' => [
    'asset' => 'locations/example',
    'alt' => '...',
    'sizes' => '100vw',
    'loading' => 'lazy',
    'fetchpriority' => 'auto',
],
```

The Feature does not create a second image pipeline.

## `locations` public section

The existing section remains:

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

It may render the complete catalog:

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

Optional presentation props are:

```text
eyebrow
title
intro
```

---

# Geographic target network

## Purpose

Geographic targets describe where a business deliberately wants to organize public geographic relevance.

They are not generated SEO pages.

A target may represent:

```text
state
city
region
market
```

`market` exists because a meaningful local search/service target does not have to be an incorporated city.

Examples include a master-planned community, commonly recognized local market, or other legitimate service/search market that should not be falsely described as a city.

## Stable target keys

`targets` is keyed by stable machine keys:

```php
'targets' => [
    'state-a' => [
        // ...
    ],
    'city-a' => [
        // ...
    ],
],
```

Target keys use:

```text
lowercase letters
numbers
hyphens
underscores
```

They are internal identifiers and should remain stable even if public copy changes.

## Target contract

A target uses this contract:

```php
'targets' => [
    'city-a' => [
        'type' => 'city',
        'title' => 'Example City',
        'summary' => 'Optional hub/card summary.',

        'parent' => 'state-a',

        // Stable key from config/pages/*.php, not a URL.
        'page' => 'city-a-page',

        // Both values are explicit so the platform never has to infer whether
        // a target is merely served or is an actual physical business location.
        'service_area' => true,
        'physical_location' => false,

        // Allowed only when physical_location is true.
        'address' => [
        ],

        'geography' => [
            'country_code' => 'US',
            'state_code' => 'AA',
            'county' => 'Example County',
            'region' => 'Example Region',
            'metro' => 'Example Metro',
        ],

        'related' => [
            'city-b',
        ],
    ],
],
```

Supported keys are:

```text
type
title
summary
parent
page
service_area
physical_location
address
geography
related
```

Unknown keys fail setup validation.

## Target types

Supported target types are:

```text
state
city
region
market
```

The platform does not infer a target's type from its name.

Use the most factually correct type. Do not label a community or marketing area as a city merely because a city keyword would be convenient.

## Service area vs physical location

Every target must explicitly configure:

```php
'service_area' => true|false,
'physical_location' => true|false,
```

These are independent facts.

Examples:

A service market with no office:

```php
'service_area' => true,
'physical_location' => false,
```

A physical office that is also a service area:

```php
'service_area' => true,
'physical_location' => true,
```

A structural parent target that is not itself being represented as a service area or office may use:

```php
'service_area' => false,
'physical_location' => false,
```

The platform does not automatically expose either boolean as public marketing copy.

Address lines are permitted only when:

```php
'physical_location' => true,
```

This prevents a service-area target from accidentally implying a physical office.

A physical target may omit an address when the client does not intend to publish one.

## Parent hierarchy

`parent` is optional.

Example:

```text
state-a
├── city-a
└── city-b
```

Configuration:

```php
'state-a' => [
    'type' => 'state',
    'parent' => null,
    // ...
],

'city-a' => [
    'type' => 'city',
    'parent' => 'state-a',
    // ...
],
```

Parents must reference configured targets.

A target may not parent itself.

Parent cycles fail validation.

The platform deliberately does not hardcode rules such as "a city must always have a state parent." Legitimate region/market hierarchies can vary. The hierarchy must be valid and acyclic, while client configuration owns the factual geographic relationship.

## Page references

`page` is optional.

When present it is a **stable configured page key**, not a URL:

```php
'page' => 'city-a-page',
```

The referenced page must exist in the normal page configuration loaded from:

```text
client/[CLIENT_KEY]/config/pages/*.php
```

The target resolver derives the public path and indexability from the ordinary `PageRepository`.

This preserves one page/SEO authority.

Geographic targets do not independently own:

```text
routes
canonical URLs
indexability
sitemap entries
page metadata
redirects
```

Those remain owned by the configured page and existing SEO infrastructure.

A target may exist without a page reference when it is needed only as structural geographic data.

## Geographic fields

`geography` is optional and deliberately narrow:

```php
'geography' => [
    'country_code' => 'US',
    'state_code' => 'FL',
    'county' => 'Brevard County',
    'region' => 'Space Coast',
    'metro' => null,
],
```

Supported keys are:

```text
country_code
state_code
county
region
metro
```

This is structured geographic data, not a general-purpose metadata bag.

`country_code`, when present, uses a two-letter code.

`state_code`, when present, is normalized to uppercase and accepts a short letters/numbers/hyphen code.

Client-facing geographic claims remain client-owned and should be researched/verified where needed.

## Related targets

Targets may explicitly identify related markets:

```php
'related' => [
    'city-b',
    'nearby-market',
],
```

Related targets must exist.

Duplicates and self-references fail validation.

The relation does not create a route or page. It gives page/section code a safe reusable internal-link source.

Relations do not have to be reciprocal unless the client intentionally configures them that way.

## Target resolver API

The Feature exposes:

```php
App\Features\Locations\GeographicTargetCatalog
```

Useful methods include:

```text
targets()
target($key)
roots()
children($parentKey)
ancestors($key)
relatedTargets($key)
pagePath($key)
selection($parent, $targets)
```

These methods normalize and validate the same selected-client target contract.

Feature code, client overrides, or future Vertical packages should use this resolver instead of reparsing `features.locations.targets` independently.

## `geographic-targets` public section

When Locations is enabled, the Feature registers:

```text
component: geographic-targets
```

Supported layouts:

```text
default
two
three
four
```

The section is intentionally a **linking/presentation surface**, not a page generator.

With no selector it renders root targets and their direct children:

```php
[
    'component' => 'geographic-targets',
    'props' => [
        'eyebrow' => '...',
        'title' => '...',
        'intro' => '...',
    ],
]
```

This is useful for a location hub such as:

```text
Locations
├── State A
│   ├── City A
│   └── City B
└── State B
    ├── City C
    └── City D
```

A section may render direct children of one parent:

```php
[
    'component' => 'geographic-targets',
    'props' => [
        'parent' => 'state-a',
    ],
]
```

or an explicit ordered target selection:

```php
[
    'component' => 'geographic-targets',
    'props' => [
        'targets' => [
            'city-b',
            'city-a',
        ],
    ],
]
```

`parent` and `targets` are mutually exclusive.

Optional presentation props are:

```text
eyebrow
title
intro
```

Unknown props and unknown target selections fail setup validation.

When a target references an ordinary configured page, the section links to the page's resolved public path.

When a target has child targets, their configured page paths are exposed as deliberate internal links.

No target copy is generated automatically.

## Public page and SEO ownership

The Locations Feature still does **not** automatically create:

```text
/locations
/locations/{slug}
state pages
city pages
market pages
facility pages
```

Clients create only the SEO pages they actually need under:

```text
client/[CLIENT_KEY]/config/pages/*.php
```

Those ordinary pages remain authoritative for:

```text
public path
canonical URL
indexability
page metadata
sitemap participation
old-platform migration destinations
```

This is intentional.

The target network can establish hierarchy and safe internal links without turning every configured geographic value into an indexable page.

## Sitemap behavior

Geographic targets do not contribute separate sitemap entries.

If a target references an indexable configured page, that page already participates through the normal static-page sitemap contract.

This prevents duplicate SEO ownership.

## Structured data behavior

This foundation does **not** automatically emit:

```text
LocalBusiness
Place
PostalAddress
service-area schema
```

The explicit:

```text
service_area
physical_location
geography
address
```

fields create a safer semantic foundation for a future reusable structured-data contributor, but schema should not be invented before the intended business/location semantics are clear.

## Setup validation

When `locations` is enabled, `setup:validate` includes both:

```text
LocationCatalog
GeographicTargetCatalog
```

validation contributors.

Catalog validation continues to cover:

- catalog configuration shape;
- stable group and item keys;
- required item titles;
- group references;
- empty configured groups;
- address-line lists;
- fact label/value contracts;
- responsive-media reference shape;
- safe links;
- supported `locations` section props;
- valid group/item section selections.

Geographic target validation covers:

- stable target keys;
- supported target types;
- explicit service-area/physical-location booleans;
- factual physical-address semantics;
- parent references;
- acyclic hierarchy;
- configured page references;
- structured geography shape;
- related-target references;
- duplicate/self related references;
- supported `geographic-targets` props;
- valid parent/target section selections;
- sections that resolve to no targets.

The validators do not verify:

```text
licensing claims
actual business coverage
search volume
rankings
market statistics
local editorial claims
```

Those remain client/editorial responsibilities.

## Client view overrides

The normal section override seam continues to apply.

Catalog section:

```text
client/[CLIENT_KEY]/resources/views/sections/locations.blade.php
```

Geographic target section:

```text
client/[CLIENT_KEY]/resources/views/sections/geographic-targets.blade.php
```

Clients should override presentation only when the shared contract cannot express a legitimate requirement.

The shared catalog/target resolvers remain the data authority.

## Deliberately deferred

This foundation does not add:

- database persistence for targets;
- a geographic-target CMS;
- automatic target-page generation;
- automatic state/city copy;
- geocoding;
- map-provider APIs;
- latitude/longitude;
- radius/service-boundary geometry;
- automatic LocalBusiness/Place schema;
- keyword-volume decisions;
- automatic city × service/program pages;
- location-specific contact/CRM workflows.

Those should be added only when real client requirements establish a reusable contract.