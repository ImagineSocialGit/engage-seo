# Media and Image Foundation

## Purpose

Engage SEO treats static client imagery as source-controlled client content that is transformed into deployment-ready responsive media.

The platform intentionally separates:

```text
static client imagery
    client-owned source files
    build-time responsive variants
    public manifest-driven rendering

future CMS/runtime uploads
    Feature-owned persistence and upload lifecycle
    not part of this foundation
```

Do not route future Blog/CMS uploads through this static build merely to reuse image encoding code. Runtime-upload requirements should establish their own storage and lifecycle contract when that Feature exists.

## Source ownership

A selected client owns raw static imagery under:

```text
client/{CLIENT_KEY}/resources/images/raw/
```

Supported source formats are:

```text
.jpg
.jpeg
.png
.webp
.avif
```

Dotfiles are ignored so the client scaffold may retain `.gitkeep`.

Visible unsupported files fail the media build and setup validation rather than being silently ignored.

Image asset keys come from the relative source path without the extension.

Example:

```text
resources/images/raw/heroes/home.jpg
```

becomes:

```text
heroes/home
```

Each path segment must use lowercase letters, numbers, hyphens, and underscores. Multiple source files may not resolve to the same asset key.

## Build command

Install the declared Node dependencies, then run from the platform root:

```bash
npm ci
npm run media:build -- [CLIENT_KEY]
```

The build script requires an explicit client key. It does not source the platform or client `.env` file.

The build:

1. scans the selected client's raw image directory;
2. auto-orients each source;
3. generates responsive AVIF and WebP variants;
4. generates a tiny WebP placeholder data URI;
5. records intrinsic dimensions and the source SHA-256;
6. writes generated files and a versioned manifest under `public/media/`.

Configured responsive widths are:

```text
320
640
960
1280
1600
```

Images are never enlarged. When the source width is smaller than a configured breakpoint, its natural width is used. Sources wider than 1600 pixels stop at 1600 pixels in this foundation.

## Generated deployment output

Generated media lives under:

```text
public/media/
    assets/
    manifest.json
```

Only:

```text
public/media/.gitignore
```

is tracked by the platform repository. Generated assets and the manifest are deployment/build artifacts.

The manifest is the runtime authority for available static image variants. Blade code must not infer variant filenames from a raw source path.

## Manifest contract

Manifest version 1 records:

```text
version
selected client key
assets keyed by stable media asset key
    raw source path
    raw source SHA-256
    intrinsic width/height
    placeholder data URI
    WebP fallback
    AVIF responsive sources
    WebP responsive sources
```

`php artisan setup:validate` checks the selected client's media state.

Among other structural checks, validation confirms:

- raw files use supported formats and safe asset keys;
- raw asset keys do not collide;
- a manifest exists when raw images exist;
- the manifest belongs to the selected client;
- manifest source hashes still match raw files;
- referenced generated files exist;
- AVIF/WebP source lists and intrinsic dimensions are structurally valid.

A selected client with no raw images does not require a manifest.

After adding or changing any raw image, rebuild media before treating setup validation as complete.

## Public URL resolution

Platform defaults live in:

```text
config/media.php
```

Local generated media resolves under:

```text
/media
```

A client that mirrors generated media to another public origin may add:

```text
client/{CLIENT_KEY}/config/media.php
```

with:

```php
<?php

return [
    'base_url' => 'https://cdn.example.com/client-media',
];
```

`base_url` may be:

- an absolute HTTP/HTTPS URL; or
- an absolute site path.

It may not contain a query string or fragment.

This is a public URL seam only. Engage SEO does not hardcode an object-storage/CDN provider or read provider credentials merely to render static images.

If deployment mirrors `public/media/assets` elsewhere, the deployment process is responsible for syncing those generated files and the configured `base_url` must point to the mirrored public prefix.

## Blade rendering

Use the platform responsive-image component:

```blade
<x-responsive-image
    asset="heroes/home"
    alt="Descriptive alternative text"
    sizes="(min-width: 64rem) 50vw, 100vw"
/>
```

The component renders:

```text
<picture>
    AVIF <source>
    WebP <source>
    WebP fallback <img>
</picture>
```

It also renders intrinsic width and height from the manifest to reserve layout space.

Supported behavioral attributes are:

```text
loading        lazy | eager
fetchpriority  auto | high | low
decoding       auto | async | sync
sizes          non-blank responsive sizes expression
```

`alt` is required by the component contract. An intentionally decorative image may pass an empty string:

```blade
alt=""
```

Do not omit `alt` merely because an image is difficult to describe.

Additional HTML attributes supplied to the component are forwarded to the fallback `<img>` element, while core source/dimension/loading attributes remain controlled by the media contract.

## SEO metadata boundary

The existing page metadata contract currently accepts public image URLs for Open Graph/Twitter defaults and overrides.

This media foundation does not silently reinterpret every metadata string as a media asset key. A later reusable section/client implementation may resolve a known media asset through `MediaAssetResolver` and pass its public URL deliberately.

Keeping that boundary explicit avoids creating two ambiguous meanings for one metadata field.

## Testing boundary

Media tests verify:

- manifest validation;
- stale-source detection;
- local and alternate public URL resolution;
- responsive source contracts;
- semantic `<picture>` output and intrinsic dimensions;
- functional component attribute validation.

Tests do not assert image appearance, client-specific copy, Tailwind classes, or visual layout.