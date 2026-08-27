# Verticals

## Purpose

Engage SEO Verticals are lightweight industry/category composition layers above reusable Features.

They are not modules, second Feature systems, client repositories, or places to store business-specific content.

The precedence rule is:

```text
Platform capabilities
    ↓
Vertical default Feature composition
    ↓
Client enabled / disabled Feature choices
    ↓
Client-owned content, pages, presentation, and integrations
```

A client may select no Vertical.

## Current executable contract

Platform Vertical definitions live in:

```text
config/verticals.php
```

Each registered Vertical currently supports only:

```php
'vertical-key' => [
    'name' => 'Internal name',
    'default_features' => [
        'feature-key',
    ],
],
```

The intentionally narrow contract prevents us from inventing vertical abstraction before repeated client implementations show what is actually reusable.

`VerticalManager` validates:

- registry shape;
- Vertical key format;
- supported definition keys;
- a non-blank internal name;
- `default_features` as a unique list of valid Feature keys;
- the selected client Vertical;
- and, through `FeatureManager`, that every registered Vertical references only real platform Features.

## Built-in Verticals

The first built-in Verticals are:

```text
mortgage
pets
```

Both currently compose:

```text
services
locations
blog
```

This is composition only.

For example, the same generic Services Feature may describe loan offerings for one client and training programs for another. The Vertical does not create a MortgageServices or PetServices runtime.

A client can disable any Vertical default in:

```text
clients/[CLIENT_KEY]/config/features.php
```

For example:

```php
return [
    'enabled' => [
    ],

    'disabled' => [
        'blog',
    ],
];
```

The client disable list is final Feature-enablement authority.

## What does not belong in a Vertical

Do not put these in platform Vertical definitions:

```text
client branding
client-facing page copy
client claims
specific services/products
specific locations
pricing
staff
reviews/testimonials
NMLS/license/compliance text
pet-training policies
application/booking destinations
provider IDs
API credentials
external account identifiers
```

Those remain client-owned or belong to an explicit reusable Feature/integration seam.

## Future Vertical contracts

A Vertical may eventually contribute reusable terminology, page/content presets, field schemas, section presets, or recommended information architecture.

Do not add those merely because one client needs them.

The first Mortgage and Pets client implementations should be used as evidence:

1. keep client-specific material in the client repository;
2. identify patterns that repeat across multiple clients in the same category;
3. promote only those repeated patterns into a documented Vertical contract;
4. promote cross-category behavior into a generic Feature instead.

This keeps Vertical code small and prevents client-specific assumptions from hardening into platform architecture.