# Old Platform Migration and SEO Cutover Safety

## Purpose

Engage SEO treats replacement of an existing public website as an SEO migration, not merely a design/deployment task.

The goal is to ensure every known legacy public URL has an explicit cutover outcome before launch:

```text
preserved
    the same public path remains a configured, indexable page

redirected
    the legacy path uses an intentional permanent redirect to a relevant replacement

retired
    the URL is deliberately removed and the reason is documented

unaccounted
    no outcome has been assigned yet; cutover audit fails
```

This foundation does not promise preservation of search rankings. It provides deterministic technical coverage so known legacy URLs are not silently lost during a rebuild.

## One redirect authority

Old-platform migration does not create a second redirect engine.

Runtime redirects remain owned by:

```text
site.seo.redirects
```

The migration inventory records the intended outcome. The audit verifies that every `redirected` row matches the actual runtime redirect, including destination and permanent status.

This keeps redirect behavior and migration documentation from drifting apart.

## Client-owned inventory

When a client is replacing an existing public site, enable:

```php
// clients/[CLIENT_KEY]/config/seo_migration.php

return [
    'enabled' => true,
    'inventory_path' => 'resources/migration/legacy-urls.tsv',
];
```

The inventory is source-controlled client state.

Default path:

```text
clients/[CLIENT_KEY]/resources/migration/legacy-urls.tsv
```

The TSV header is exactly:

```text
path	outcome	target	notes
```

Example:

```text
path	outcome	target	notes
/about	preserved		
/old-services	redirected	/services	
/obsolete-promotion	retired		Expired campaign with no replacement
/unreviewed			
```

A blank outcome is intentionally treated as `unaccounted`. This allows a discovered URL list to be entered first and classified deliberately afterward.

## Inventory rules

Legacy `path` values:

- are selected-site absolute paths beginning with `/`;
- do not contain query strings or fragments;
- normalize trailing slashes consistently with runtime redirects;
- may appear only once in the inventory.

Supported outcomes:

```text
preserved
redirected
retired
blank -> unaccounted
```

`preserved` rows do not define a target.

`redirected` rows require a target that is either:

- an absolute selected-site path; or
- an absolute HTTP/HTTPS URL.

`retired` rows require a note explaining the retirement and do not define a target.

## Audit command

Run:

```bash
php artisan seo:migration:audit
```

The command exits non-zero when coverage is incomplete or contradictory.

For preserved paths, the audit verifies:

- the path does not have a runtime redirect;
- a configured public page exists at the same path;
- the page is intended to be indexable;
- the page canonical resolves to the same path.

For redirected paths, the audit verifies:

- a matching runtime redirect exists;
- the inventory target exactly matches the runtime target;
- the redirect is permanent (`301` or `308`);
- an internal target resolves to an intended-indexable configured page;
- an internal target self-canonicalizes.

External redirect targets are allowed, but the audit warns that their availability and indexability cannot be verified locally.

For retired paths, the audit verifies:

- a non-blank retirement note exists;
- the path no longer resolves to a configured public page;
- no runtime redirect remains.

## Setup validation

When `seo_migration.enabled` is false, normal client setup does not require a legacy URL inventory.

When it is true:

```bash
php artisan setup:validate
```

also includes old-platform migration audit failures.

This makes an enabled migration part of the selected client's launch-readiness contract.

## Discovery versus audit

This foundation deliberately separates URL discovery from deterministic cutover validation.

Potential discovery sources include:

- the old site's XML sitemap;
- a crawl of the existing public site;
- Google Search Console exports;
- backlink/SEO-tool exports;
- manually identified campaign or historical URLs.

Those sources should be consolidated into the client-owned inventory.

The platform audit does not crawl a remote site during setup validation. This keeps validation reproducible, fast, and independent of network availability.

## Unknown URLs

Do not add a catch-all redirect from unknown old URLs to the homepage merely to avoid 404 responses.

A legacy URL should be preserved, redirected to a genuinely relevant replacement, or explicitly retired.

## Feature-owned destinations

This foundation verifies configured static pages as preserved/internal redirect destinations.

As dynamic Features such as Locations and Blog are introduced, their migration-aware destination contribution contract should be added deliberately rather than treating arbitrary route matches as proof that a legacy destination exists.