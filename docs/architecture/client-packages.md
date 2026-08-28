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

## Package-owned environment keys

Private packages may need selected-client credentials without teaching the generic platform about each provider. A selected client declares those keys alongside its package providers:

```php
client/{CLIENT_KEY}/config/client_packages.php

return [
    'providers' => [
        Vendor\\Package\\PackageServiceProvider::class,
    ],

    'environment_keys' => [
        'ENGAGE_SEO_EXAMPLE_PROVIDER_KEY',
    ],
];
```

Package-declared keys must start with `ENGAGE_SEO_` and contain only uppercase letters, numbers, and underscores. This namespace rule prevents a package from taking ownership of process-level Laravel variables such as `APP_ENV`, `APP_KEY`, `CLIENT_KEY`, `DB_HOST`, or queue/cache drivers.

Declared package keys become part of the selected-client `.env` allowlist for that client only. They are cleared before the selected client's `.env` is loaded just like the platform's built-in client-owned keys. They are also included in setup validation and root/client ownership checks.

The package itself remains responsible for deciding whether a declared credential is actually required for the selected configuration. Do not put secrets in `config/client_packages.php`; declare only the environment variable name there and keep the real value in the selected client's uncommitted `.env`.

## Private GitHub repositories

Private GitHub repositories may be referenced through a Composer VCS repository, for example:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:ImagineSocialGit/engage-seo-vertical-mortgage.git"
        }
    ]
}
```

Local development can use the developer's normal GitHub credentials or Composer GitHub token. Staging and production should use an appropriate read-only deployment credential.

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

## Initial installation and locked installs

When a client gets its first Composer package and does not yet have a `composer.lock`, run a full update from that client repository:

```bash
cd client/{CLIENT_KEY}
composer update
```

That creates the initial `composer.lock`. Commit the lock file in the client repository.

Once a lock file exists, targeted dependency changes may use normal Composer update commands such as:

```bash
composer update vendor/package --with-dependencies
```

Normal staging/production deployment should install exactly what the committed lock file specifies:

```bash
cd client/{CLIENT_KEY}
composer install --no-dev --prefer-dist --optimize-autoloader
```

The exact deployment automation that invokes the selected client's Composer install belongs in the deployment workflow and should be kept aligned with the current Engage Core/Engage SEO operations contract rather than duplicated ad hoc.

## Local package development

Do not require a commit, GitHub push, and Composer update for every local edit to a reusable package.

Engage SEO provides a local-only development link workflow that temporarily replaces the selected client's Composer-installed package directory with a symlink to a local package checkout while leaving the client's committed `composer.json` and `composer.lock` unchanged.

For example:

```bash
cd /var/www/engage-seo

./scripts/dev-link-package.sh \
    engage-seo/vertical-mortgage \
    /var/www/engage-seo-vertical-mortgage
```

The script:

- runs only when the root `APP_ENV` resolves to `local`;
- uses the selected root `CLIENT_KEY` unless an explicit matching client key is supplied;
- verifies that the client actually requires the requested package;
- verifies that the local package `composer.json` declares the same package name;
- requires the normal Composer-managed package to be installed first;
- replaces only that installed package directory with a symlink to the local checkout;
- does not rewrite the client's `composer.json` or `composer.lock`;
- clears compiled Blade views after linking.

While linked, ordinary PHP, Blade, CSS, and JavaScript edits that are read from the package checkout are available to the selected client without pushing Git or running Composer again.

The current Mortgage Calculator package reads its CSS and JavaScript directly from the package at render time, so calculator CSS/JavaScript edits require only a browser refresh after the local package is linked. They do not require a root Vite build.

If a package changes its own Composer dependency or autoload contract, treat that as a dependency change rather than a normal source edit: restore the Composer-managed package, update the client's dependency normally, and then link the local checkout again.

To restore the exact Composer-managed revision pinned in the selected client's lock file:

```bash
./scripts/dev-unlink-package.sh engage-seo/vertical-mortgage
```

Before replacing the symlink, the unlink script verifies that the locked source repository is readable. It then uses Composer `reinstall` so the committed lock file remains authoritative. The script verifies that `composer.lock` did not change during the restore.

Local package links are development state only. Never create or rely on these symlinks in staging or production.

## Frontend assets

Small package-specific assets may remain physically inside the private package and render only when the package section is used. This keeps unrelated client deployments from carrying vertical-only CSS or JavaScript.

If package frontend assets become large or numerous, add a documented host build/publish seam rather than importing optional private package assets unconditionally into the root Vite bundle.

## Testing boundary

The platform tests the generic selected-client package loader and provider contract.

Each private package owns tests for its own reusable behavior. Client repositories should not add tests that freeze client-facing copy.