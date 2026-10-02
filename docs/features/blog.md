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
client/{CLIENT_KEY}/config/features/blog.php
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

When an established Blog/Learning Center namespace is intentionally renamed, configure
known previous base paths instead of abandoning existing article URLs:

```php
return [
    'path' => '/resources',
    'legacy_paths' => [
        '/learning-center',
    ],
];
```

A legacy base path owns scoped 301 preservation routes for the old index, category, and
published article URLs. Published article/category slugs are carried to the current base path.
Unknown or unpublished old article slugs remain 404s; the legacy namespace is not a catch-all
redirect to unrelated content.

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

All three public Blog surfaces receive the same normalized sidebar-support data:

```text
categories
recentPosts
```

`recent_posts_limit` controls the number of most recently published posts in that collection and defaults to `5`. A value of `0` disables the recent-post collection. Client views may use these values for archive/article sidebars without querying Eloquent from Blade.

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

Optional `author_profiles` may map the exact `author_name` stored on a Blog post to a
stable person in `site.people`. When mapped, the visible byline links to the person's public
profile and Article JSON-LD references that Person URL/ID. Unmapped author names continue to
render as plain names.

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
client/{CLIENT_KEY}/resources/views/features/blog/index.blade.php
client/{CLIENT_KEY}/resources/views/features/blog/category.blade.php
client/{CLIENT_KEY}/resources/views/features/blog/show.blade.php
```

The normalized public data contract remains owned by the Feature even when presentation is overridden.

## Selected-client bootstrap

Existing sites often need historical Blog content to exist immediately in every fresh environment.

When Blog is enabled, the Feature registers the `blog` selected-client bootstrap contributor. Client-owned initial state belongs at:

```text
client/{CLIENT_KEY}/bootstrap/blog.php
```

The payload uses the same validated Blog section shape as editorial promotion:

```text
version
categories
posts
```

Run it only after migrations and after static media referenced by the payload has been built/deployed:

```bash
php artisan client:bootstrap
```

The Blog contributor requires empty Blog editorial tables on first application and reuses the existing Blog snapshot validation/application contract. The generic bootstrap runner records the source version/checksum and skips an identical rerun.

Bootstrap never becomes an ongoing synchronization mechanism. Once Blog editorial rows exist, later reviewed changes move through the editorial promotion workflow or an explicit data migration.

See:

```text
docs/operations/client-bootstrap.md
```

## Editorial promotion boundary

Blog remains a staging-authored, production-served editorial capability. This foundation does not expose a general production CMS login.

Staging and production databases remain separate. Reviewed Blog state moves through the platform editorial snapshot workflow rather than through a database copy.

A Blog post is eligible for promotion when `published_at` is non-null. This includes posts already public on staging and posts deliberately scheduled for a future publication time. Drafts with `published_at=null` remain staging-only and are excluded from the promotion snapshot.

Only categories attached to promotable posts move with the snapshot. Production therefore receives the exact public/scheduled Blog state rather than staging-only drafting state.

Use:

```bash
php artisan editorial:export
php artisan editorial:validate /path/to/snapshot.json
php artisan editorial:import /path/to/snapshot.json --force
```

The snapshot preserves Blog timestamps so Article `datePublished` / `dateModified` semantics do not reset merely because content was promoted.

Production import creates an automatic protected rollback snapshot before replacing Blog editorial rows. A production rollback snapshot may be re-imported into production; normal forward promotion must originate from staging. Staging does not accept editorial snapshot imports; it remains the authoring/review database.

The snapshot contains editorial content only. It never carries `.env` values, database credentials, cache/Redis namespaces, future Engage Core integration credentials, or whole-database state.

See:

```text
docs/operations/editorial-promotion.md
```

## Staging editing boundary

This slice defines the promotion mechanism, not the browser-based staging editor. Until a staging-only editorial UI is implemented, do not add a general production administration surface merely to create Blog records.

The future editing UI must preserve the same rule: editing occurs outside production, reviewed state is promoted explicitly, and production serves the promoted snapshot.

## Migration and deployment

After enabling Blog for a selected client, run in each fresh environment:

```bash
php artisan migrate
php artisan client:bootstrap
```

If the client has no Blog bootstrap source, the second command is a no-op for Blog.

The normal environment rules still apply:

```text
staging Engage SEO Sites -> staging dependencies only
production Engage SEO Sites -> production dependencies only
```

Deploy code/config/static media first, run target migrations, validate the editorial snapshot against production, then import it. Do not copy the staging database into production.

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
- scoped legacy Blog namespace redirects;
- author-profile mapping;
- route-space/configuration validation.

Tests do not freeze client article titles, marketing wording, visual classes, or exact client-facing copy.