# Selected-Client Composer Packages

## Purpose

Engage SEO keeps the generic platform install small and reusable. Code that is genuinely relevant only to a subset of clients may live in a private Composer package installed by those selected client repositories instead of being shipped in every Engage SEO deployment.

This is intended for real runtime isolation, not merely hiding an installed Feature behind a configuration flag.

Examples include:

```text
mortgage-specific calculators
mortgage-specific provider integrations
pet-specific runtime integrations
other vertical-only application code or assets
```

Generic capabilities such as Services, Locations, Blog, rendering, SEO, accessibility, and client loading remain in the platform.

## Client dependency ownership

A client repository may optionally own:

```text
client/{CLIENT_KEY}/composer.json
client/{CLIENT_KEY}/composer.lock
client/{CLIENT_KEY}/config/client_packages.php
```

The client `composer.lock` is part of the deployment contract and should be committed. It pins the exact package revisions paired with that client.

The nested client vendor directory is runtime output and must not be committed:

```text
client/{CLIENT_KEY}/vendor/
```

Clients that need no external package do not need a `composer.json` at all.

## Runtime loading

When a selected client has a `composer.json`, Engage SEO requires that client's Composer autoloader before loading client PHP configuration.

After client configuration is merged, the platform reads:

```php
client/{CLIENT_KEY}/config/client_packages.php

return [
    'providers' => [
        Vendor\\Package\\PackageServiceProvider::class,
    ],
];
```

Each configured class must exist and extend Laravel's `ServiceProvider`.

Package providers register before `FeatureManager` validates and registers enabled Features. That lets a package contribute documented platform extensions such as Feature registry entries before the selected client's Feature choices are resolved.

## Private GitHub repositories

Private GitHub repositories may be referenced through a Composer VCS repository, for example:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:OWNER/engage-seo-vertical-mortgage.git"
        }
    ]
}
```

Local development can use the developer's normal GitHub SSH credentials. Staging and production should use an appropriate read-only deploy key or other approved GitHub SSH identity.

Repository authentication is deployment infrastructure. Do not put GitHub credentials or private keys in client PHP config or committed environment files.

## Package boundaries

A selected-client package should extend documented Engage SEO seams instead of reaching into unrelated private implementation details.

Good package extension points include:

```text
Feature registration
reusable section registration
setup validation contributors
SEO contributors
views owned by the package
package-owned configuration
package-owned frontend assets
```

Do not move client-specific copy, branding, destination URLs, credentials, or secrets into a reusable Vertical package.

An internal package installed beneath a client repository should also avoid installing a second Laravel framework dependency tree. The Engage SEO host supplies Laravel and the documented Engage SEO contracts; the package supplies only its own runtime code and assets.

## Installation workflow

For the first local install of a newly added private package:

```bash
cd client/{CLIENT_KEY}
composer update vendor/package --with-dependencies
```

Commit the generated `composer.lock` in the client repository.

Normal staging/production deployment should use:

```bash
cd client/{CLIENT_KEY}
composer install --no-dev --prefer-dist --optimize-autoloader
```

The exact deployment automation that invokes the selected client's Composer install belongs in the deployment workflow and should be kept aligned with the current Engage Core/Engage SEO operations contract rather than duplicated ad hoc.

## Frontend assets

Small package-specific assets may remain physically inside the private package and render only when the package section is used. This keeps unrelated client deployments from carrying vertical-only CSS or JavaScript.

If package frontend assets become large or numerous, add a documented host build/publish seam rather than importing optional private package assets unconditionally into the root Vite bundle.

## Testing boundary

The platform tests the generic selected-client package loader and provider contract.

Each private package owns tests for its own reusable behavior. Client repositories should not add tests that freeze client-facing copy.