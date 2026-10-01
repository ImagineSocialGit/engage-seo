# People and Breadcrumb SEO Foundation

## Purpose

Engage SEO keeps reusable SEO structure in the platform while leaving business identity and page content in the selected client.

This foundation adds two generic client-owned contracts:

- `site.people`: stable public person profiles that may be reused by biography pages and Blog author bylines.
- `pages.*.breadcrumbs`: visible breadcrumb trails that also produce `BreadcrumbList` structured data.

It also emits a generic site `Organization` node from the normalized site/business presentation contract and supports page-level `Person` structured data when an ordinary configured page references a person key.

## Site people

Selected clients may define:

```php
'people' => [
    'person-key' => [
        'name' => 'Public Name',
        'url' => '/about-person',
        'job_title' => 'Public role',
        'description' => 'Short factual description.',
        'same_as' => [
            'https://example.com/public-profile',
        ],
    ],
],
```

Person keys are stable machine keys. Client-specific names, biographies, credentials, claims, profile URLs, and social identities stay in the client repository.

`url` may be an absolute site path or HTTP/HTTPS URL. `same_as` accepts only absolute HTTP/HTTPS URLs.

## Page person reference

An ordinary configured page may opt into page-level Person structured data:

```php
return [
    'path' => '/about-person',
    'person' => 'person-key',
    'meta' => [
        // ...
    ],
    'sections' => [
        // visible biography content
    ],
];
```

The referenced person must exist in `site.people`. The generated Person node points back to the site Organization node through `worksFor`.

The platform does not infer a Person from arbitrary page copy.

## Breadcrumbs

An ordinary configured page may define:

```php
'breadcrumbs' => [
    [
        'label' => 'Home',
        'url' => '/',
    ],
    [
        'label' => 'Topic',
        'url' => '/topic',
    ],
],
```

Breadcrumbs are rendered visibly above page sections and emitted as `BreadcrumbList` JSON-LD. Each configured item therefore needs both a visible label and a real destination URL.

Do not create breadcrumbs for pages that do not have a meaningful hierarchy.

## Blog author profiles

The Blog Feature keeps the existing database `author_name` field as editorial content. A selected client may map an exact public author name to a stable site person:

```php
'author_profiles' => [
    'Public Name' => 'person-key',
],
```

When mapped, the public article byline links to the person profile and Article structured data uses the richer Person identity. Unmapped author names continue to render as plain bylines.

This avoids duplicating a second person database inside the Blog Feature and keeps client identity authoritative in client configuration.

## Structured data boundaries

The platform emits only data supported by normalized, visible site/page contracts:

- `Organization` from site identity and public business/contact data;
- `Person` when a page explicitly references a configured person;
- `BreadcrumbList` when a page explicitly defines visible breadcrumbs;
- existing Article/CollectionPage nodes from the Blog Feature.

Client-specific professional claims must be substantiated before launch. Structured data is not a place to add claims that are absent from visible page content.


## FAQ structured data

The generic `faq` page section is also a structured-content source. When a resolved
ordinary page contains one or more FAQ sections, the platform emits one `FAQPage`
JSON-LD node from the same visible question-and-answer content. Client config does
not maintain a second schema-only copy.

Only non-blank visible questions and answers are emitted. This contributor does not
claim rich-result eligibility; it keeps the machine-readable representation aligned
with the page content.