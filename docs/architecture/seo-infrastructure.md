# SEO Infrastructure Foundation

## Purpose

Engage SEO treats crawl/index policy as platform infrastructure rather than as a property of whichever deployment happens to be running.

The foundation owns:

```text
environment-aware indexability
robots.txt
sitemap.xml
redirects for config-owned public pages
page JSON-LD
Feature contribution seams for sitemap and structured data
```

Page titles, descriptions, canonicals, Open Graph, and Twitter metadata continue to use the existing public-page metadata contract.

## Site-wide indexing gate

Client configuration contains an explicit launch switch:

```php
'seo' => [
    'indexing_enabled' => false,
],
```

A page may be effectively indexable only when all of these are true:

```text
APP_ENV=production
a real client is selected
site.seo.indexing_enabled=true
the page-level/default indexable flag is true
```

Non-production environments are therefore always `noindex,nofollow`.

A new client scaffold starts with:

```text
indexing_enabled=false
```

Production indexing must be deliberately enabled when the site is ready to launch.

Page-level `indexable=false` remains useful for deliberately excluded production pages.

## robots.txt

`GET /robots.txt` is generated dynamically by Engage SEO.

When site-wide indexing is blocked:

```text
User-agent: *
Disallow: /
```

When site-wide indexing is allowed:

```text
User-agent: *
Allow: /
```

and the sitemap URL is included when sitemap output is enabled.

The old static `public/robots.txt` is removed so web-server static-file handling cannot bypass the selected-client/environment policy.

## Sitemap

`GET /sitemap.xml` is available only when:

```text
site-wide indexing is allowed
site.seo.sitemap_enabled=true
```

Otherwise it returns `404`.

The platform sitemap includes configured public pages only when their resolved metadata reports them as effectively indexable.

Sitemap locations must use the same origin as the selected site's `APP_URL`.

Duplicate URLs are de-duplicated.

## Feature sitemap contributions

Features must not edit platform sitemap code to add their own dynamic URLs.

A Feature may implement:

```text
App\Contracts\Seo\SitemapContributor
```

and register the contributor during provider registration through:

```php
app(\App\Support\Seo\SeoExtensionRegistry::class)
    ->registerSitemapContributor(MyContributor::class);
```

The contributor returns a list shaped as:

```php
[
    [
        'loc' => 'https://example.com/public-url',
    ],
]
```

The platform still applies the site-wide indexing gate, same-origin validation, and URL de-duplication.

A Feature contributor is responsible for returning only its own public/indexable runtime records.

## Redirects

Client redirects live under:

```text
site.seo.redirects
```

Example:

```php
'redirects' => [
    [
        'from' => '/old-path',
        'to' => '/new-path',
        'status' => 301,
    ],
],
```

Supported statuses are:

```text
301
302
307
308
```

`from` must be an absolute site path without query string or fragment.

`to` may be:

```text
/an-absolute-site-path
https://external.example/path
http://external.example/path
```

Duplicate sources, self-redirects, redirect chains, and redirect cycles fail setup validation.

Redirects are evaluated inside the generic public-page controller before config-owned page resolution.

That preserves this route precedence:

```text
explicit platform/Feature route
redirect for fallback path
config-owned public page
404
```

A client redirect cannot silently take over an explicit Feature route.

## Structured data / JSON-LD

A configured page may declare JSON-LD nodes inside its metadata:

```php
'meta' => [
    'structured_data' => [
        [
            '@context' => 'https://schema.org',
            '@type' => '...',
        ],
    ],
],
```

`structured_data` must be a list of associative arrays.

The platform head renders each resolved node as:

```text
<script type="application/ld+json">
```

using JSON encoding appropriate for embedding in HTML.

## Feature structured-data contributions

Features may contribute page-specific JSON-LD without taking ownership of the platform head.

Implement:

```text
App\Contracts\Seo\StructuredDataContributor
```

and register it through:

```php
app(\App\Support\Seo\SeoExtensionRegistry::class)
    ->registerStructuredDataContributor(MyContributor::class);
```

A contributor receives the normalized:

```text
$page
$site
```

contracts and returns a list of associative-array JSON-LD nodes.

Static page-config nodes are preserved and Feature-contributed nodes are appended.

## Setup validation

`php artisan setup:validate` validates:

- the site-wide SEO booleans;
- the redirect list shape;
- redirect source/destination/status contracts;
- duplicate redirect sources;
- redirect chains/cycles;
- page structured-data list/node shapes through page metadata validation.

Feature contributors are code contracts. Invalid contributor classes fail immediately when a Feature attempts to register them, and invalid contributor output fails rather than being silently omitted.

## Testing boundary

SEO tests cover policy behavior and data/runtime contracts.

They may verify:

```text
non-production noindex behavior
robots allow/disallow behavior
sitemap inclusion/exclusion
redirect behavior
contributor contracts
JSON-LD structural output
```

Tests must not assert client-specific marketing copy or client-facing wording.