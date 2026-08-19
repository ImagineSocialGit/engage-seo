# Services Feature

## Purpose

The Services Feature provides one reusable, client-owned catalog for public
business offerings.

It is intentionally generic. A mortgage client may use it for loan programs,
a pet-services client may use it for training programs, and another vertical
may use the same runtime for a different set of offerings.

Do not create industry-specific service Features when the underlying behavior
is the same.

## Enablement

The platform registers the Feature key:

```text
services
```

A client enables it in:

```text
clients/[CLIENT_KEY]/config/features.php
```

```php
<?php

return [
    'enabled' => [
        'services',
    ],

    'disabled' => [
    ],
];
```

When disabled:

- no Services section is registered;
- Services configuration is not required;
- no Services runtime is added to public pages.

## Client configuration

The Feature reads:

```text
clients/[CLIENT_KEY]/config/features/services.php
```

The platform default is:

```php
<?php

return [
    'groups' => [],
    'items' => [],
];
```

Group and item keys are stable machine keys.

Example shape:

```php
<?php

return [
    'groups' => [
        'group-key' => [
            'title' => '[CLIENT GROUP TITLE]',
            'intro' => null,
        ],
    ],

    'items' => [
        'service-key' => [
            'title' => '[CLIENT SERVICE TITLE]',
            'summary' => '[CLIENT SUMMARY]',
            'group' => 'group-key',

            'image' => [
                'asset' => 'services/service-key',
                'alt' => '[CLIENT ALT TEXT]',
            ],

            'facts' => [
                [
                    'label' => '[LABEL]',
                    'value' => '[VALUE]',
                ],
            ],

            'link' => [
                'label' => '[LINK LABEL]',
                'url' => '/destination',
                'new_tab' => false,
            ],
        ],
    ],
];
```

## Groups

Groups are optional.

When any group is configured:

- every service item must reference a configured group;
- empty groups fail setup validation;
- group order follows client configuration order.

When no groups are configured, the catalog is rendered as one flat offering
collection.

## Service items

Each service item requires:

```text
title
```

Optional fields are:

```text
summary
group
image
facts
link
```

`facts` is a generic label/value list. It may represent details such as
duration, starting price, eligibility, format, or another client-owned fact.
The platform does not assign industry meaning to those values.

`link` supports:

```text
absolute site paths
http
https
mailto
tel
```

External links are identified by the shared site-link normalizer. `new_tab`
is always explicit rather than inferred.

## Images

A service image uses the shared static-media contract.

At minimum:

```php
'image' => [
    'asset' => 'services/example',
    'alt' => '[ALT TEXT]',
],
```

`alt` must be explicitly present as a string. An intentionally decorative
image may use:

```php
'alt' => '',
```

Optional media presentation values are:

```text
sizes
loading
fetchpriority
```

The Services Feature does not create a second image pipeline.

## Public section

When enabled, the Feature registers:

```text
component: services
```

Supported layouts:

```text
default
two
three
```

A normal page may render the complete catalog:

```php
[
    'component' => 'services',
    'layout' => 'three',
    'props' => [
        'eyebrow' => '[OPTIONAL]',
        'title' => '[OPTIONAL]',
        'intro' => '[OPTIONAL]',
    ],
],
```

A page may render one group:

```php
'props' => [
    'group' => 'group-key',
],
```

Or one explicit ordered subset:

```php
'props' => [
    'items' => [
        'service-key',
        'another-service-key',
    ],
],
```

A section may select `group` or `items`, not both.

Unknown selections and unsupported props fail setup validation.

## Page ownership

This foundation deliberately does **not** create automatic `/services/*`
routes.

Clients compose ordinary static public pages using the existing page
configuration system and place the Services section where appropriate.

That keeps:

- paths client-owned;
- canonical metadata in the normal page contract;
- sitemap inclusion in the normal page contract;
- old-platform migration auditing compatible with the same public-page
  authority;
- vertical terminology out of the Feature runtime.

If a future client demonstrates a real requirement for independently managed
service-detail routes, the Feature can grow that behavior without changing the
catalog contract.

## Setup validation

The first concrete Feature establishes a generic setup-validation contribution
seam.

An enabled Feature may register a class implementing:

```text
App\Contracts\SetupValidation\SetupValidationContributor
```

The shared `setup:validate` command then includes that contributor's errors.

For Services, validation covers:

- catalog shape;
- stable group/item keys;
- required titles;
- group references;
- empty groups;
- image contracts;
- fact contracts;
- safe links;
- Services-section selection/prop contracts.

## Client overrides

The platform Services section participates in the existing section override
seam.

A selected client may override:

```text
resources/views/sections/services.blade.php
```

The client override must preserve the normalized Services catalog and public
accessibility/security contracts. Styling differences do not justify a new
Feature.

## Persistence and CMS boundary

This foundation is configuration-backed and introduces no database tables.

That is intentional while the first client implementations establish the
actual editing requirements.

A future staging/admin CMS may persist service catalog content, but it should
preserve the same normalized public runtime contract rather than forcing
client pages to depend directly on storage details.