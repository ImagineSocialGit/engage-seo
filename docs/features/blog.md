# Blog / Learning Center Feature

## Purpose

The Blog Feature owns reusable database-backed editorial content for Engage SEO Sites.

A client may present the feature as:

```text
Blog
Learning Center
Resources
Insights
News
```

The public label is client-owned. The runtime remains the generic `blog` Feature.

The first two planned client implementations justify the capability as a reusable content/SEO engine rather than a mortgage-specific feature.

## Enablement

Enable it through the normal client Feature contract:

```php
return [
    'enabled' => [
        'blog',
    ],

    'disabled' => [],
];
```

Optional client overrides belong at:

```text
clients/{CLIENT_KEY}/config/features/blog.php
```

## Public URL contract

The configurable base path defaults to:

```text
/blog
```

A client may override it, for example:

```php
return [
    'path' => '/learning-center',
];
```

The Feature owns:

```text
GET /learning-center
GET /learning-center/category/{category-slug}
GET /learning-center/{post-slug}
```

The fixed `category` path segment owns only `/category/{category-slug}`; ordinary one-segment post slugs remain independent.

The base path must use lowercase URL-safe segments and may not collide with platform routes, configured static pages, or runtime redirect sources.

## Persistence

The Feature owns:

```text
blog_categories
blog_posts
blog_category_post
```

The migration is Feature-local and is loaded only when Blog is enabled for the selected client.

A post is publicly visible only when:

```text
published_at is not null
published_at <= current time
```

Future `published_at` values therefore behave as scheduled publication times.

`indexable=false` keeps a published post out of Blog sitemap contribution and resolves its page metadata as noindex through the normal site-wide indexing policy.

## Categories

Categories support:

- stable slugs;
- display names;
- descriptions;
- category-specific meta title/description;
- sort order;
- independent indexability.

Categories are many-to-many with posts.

The default public index shows category navigation for categories that contain published posts.

## Featured content

Posts may set:

```text
featured=true
```

The Blog index can render a configurable number of the most recently published featured posts before the main article archive.

This supports Learning Center-style landing pages with a small high-priority content row without creating a client-specific homepage system.

## Structured article content

Blog bodies do not store or render arbitrary trusted HTML.

`blog_posts.content` is a JSON list of typed blocks.

Supported block types are:

```text
paragraph
heading (H2/H3)
list
quote
links
image
```

Text is escaped by Blade.

The `links` block provides explicit internal/external linking without permitting script URLs or arbitrary markup.

The `image` block references the existing static client media contract and requires an explicit alt string.

This does **not** make the static media pipeline the future runtime-upload system for CMS images. A later administrative upload workflow must establish its own durable storage lifecycle before accepting browser-uploaded Blog media.

## Featured images

A post may reference a static client media asset through:

```text
featured_image_asset
featured_image_alt
```

If an asset is present, the alt value must be explicitly present as a string. An empty string remains available when an image is intentionally decorative.

The public renderer uses the existing responsive image component.

## SEO

The Blog Feature reuses `PageMetaResolver` and the site-wide indexing policy.

Public post metadata supports:

```text
meta title
meta description
canonical URL
indexability
Open Graph article type
featured image
Article JSON-LD
```

The Feature contributes intended indexable URLs through the existing `SitemapContributor` seam:

```text
Blog index
indexable categories containing indexable published posts
indexable published posts
```

The old-platform migration auditor now recognizes indexable Feature-owned sitemap URLs as valid preserved or redirect destinations. This allows an old WordPress article to redirect directly to a new Blog Feature article without pretending the article is a configured static page.

## Client view seam

The Feature provides platform views:

```text
features.blog.index
features.blog.category
features.blog.show
```

A selected client may override them explicitly at:

```text
clients/{CLIENT_KEY}/resources/views/features/blog/index.blade.php
clients/{CLIENT_KEY}/resources/views/features/blog/category.blade.php
clients/{CLIENT_KEY}/resources/views/features/blog/show.blade.php
```

The normalized public data contract remains owned by the Feature even when presentation is overridden.

## Production admin boundary

This batch establishes the **public database-backed Blog runtime**, not the editing/promotion workflow.

It intentionally does not expose a production CMS login.

Staging and production databases remain separate. A later Blog publishing slice must define how reviewed/published editorial state moves from staging to production without copying an entire database or exposing general production administration.

Until that promotion contract exists, do not assume that creating a Blog record in staging automatically creates it in production.

## Migration and deployment

After enabling Blog for a selected client, run:

```bash
php artisan migrate
```

The normal environment rules still apply:

```text
staging Engage SEO Sites -> staging dependencies only
production Engage SEO Sites -> production dependencies only
```

Blog database content is runtime data, not an environment credential and not a substitute for the future explicit editorial promotion contract.

## Testing boundary

Generic Blog tests cover:

- optional Feature registration;
- Feature routes;
- database schema/runtime publication behavior;
- draft/future-publication exclusion;
- category filtering;
- structured content safety;
- sitemap contribution;
- old-platform redirect coverage into Feature-owned URLs;
- route-space/configuration validation.

Tests do not freeze client article titles, marketing wording, visual classes, or exact client-facing copy.