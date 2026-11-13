# SEO selected-client package operator lifecycle

## Verified ownership boundaries

- The selected SEO client owns its `composer.json`, `composer.lock`, and `vendor/` installation. The SEO platform root `vendor/` is not where optional selected-client Composer packages are installed.
- `app/Support/Clients/ClientPackageLoader.php` loads the selected client's Composer autoloader, then registers provider classes from `client/<client>/config/client_packages.php` through the platform's own configuration system.
- The package being Composer-installed is not evidence that its service provider is declared, its optional feature/vertical is active, or any migration has run.
- The existing `scripts/operations/lib/package_audit_workflow.py` inspects Composer manifests, locks, source descriptors and installed paths **without evaluating PHP configuration**. Its provider/feature/migration findings remain explicitly *not verified*.
- The platform's public `scripts/package.sh` is the operator-facing entrypoint for the currently supported workflows. The actual local link/unlink operations remain owned by `scripts/dev-link-package.sh` and `scripts/dev-unlink-package.sh`.

## Operator commands

Read-only client inventory and source descriptor check:

```bash
./scripts/package.sh audit CLIENT_KEY --json
./scripts/package.sh audit CLIENT_KEY --source /absolute/path/to/package --json
```

Local development source link (mutates selected client's installed source, subject to the existing selected-client and `APP_ENV=local` guards):

```bash
./scripts/package.sh link CLIENT_KEY /absolute/path/to/package
```

Return an explicitly selected package to the locked Composer-managed installation:

```bash
./scripts/package.sh unlink CLIENT_KEY vendor/package
```

Both link and unlink use the pre-existing protected SEO helpers. They are **not** supported for staging or production. The wrapper does not open `.env` or execute package code to discover package names; selected-client and local environment authority are verified by the helpers before mutation.

## Shared local revision operations

With Infrastructure B51 installed at `~/Projects/engage-infrastructure`, SEO
and Core support identical package revision CLI syntax:

```bash
./scripts/package.sh update CLIENT_KEY vendor/package --plan --json
./scripts/package.sh update CLIENT_KEY vendor/package --apply --confirm-plan REVIEWED_FINGERPRINT --json
./scripts/package.sh repair CLIENT_KEY vendor/package --plan --json
./scripts/package.sh repair CLIENT_KEY vendor/package --apply --confirm-plan REVIEWED_FINGERPRINT --json
```

Set `ENGAGE_INFRASTRUCTURE_ROOT=/absolute/path/to/engage-infrastructure`
only if using another location. The SEO entrypoint binds the actual SEO root,
`--platform seo` and `--env local`. Passing different root, client, environment
or package selection flags is rejected. The shared engine reads the selected
client's existing direct Composer dependency and lock, verifies installed
provenance, and requires explicit reviewed approval before mutation. A normal
update may change only the selected locked package; repair is lock-pinned.

The shared engine refuses a client with development-linked dependencies.
An absent or incomplete Infrastructure checkout causes a clear failure for
`update`/`repair`, not an unreviewed generic Composer fallback. The pre-existing
`audit`, `link` and `unlink` commands work without Infrastructure. No
provider registration, feature activation, migrations, or runtime credentials
are changed by these revision operations. A successful revision remains subject
to SEO's provider/feature checks and application tests.

## Outstanding package lifecycle stages

SEO still does not expose a managed package **install** or scaffold/adoption workflow comparable to Core. Adding a new direct Composer requirement and provider declaration remains a separate explicit lifecycle stage; `update` and `repair` only reconcile already-required, locked packages.

Provider registration, feature/vertical activation, and schema migrations are also **separate authorities**. A successful audit or link must not be reported as functional readiness. The platform's package loader and feature manager remain authoritative; there is no new source-based discovery/activation path in this batch.

For deployment, verify: the intended client is selected; the locked dependency is actually installed; provider and feature configuration match the selected capability; migrations and external/internal credentials have their own readiness evidence; and no development symlink remains. This is a contract to enforce in future orchestration, **not** a claim that all of those gates already exist.

## Source basis

- `scripts/package.sh`, `scripts/dev-link-package.sh`, `scripts/dev-unlink-package.sh`
- `scripts/operations/lib/package_audit_workflow.py`
- `app/Support/Clients/ClientPackageLoader.php`
- `client/<client>/config/client_packages.php`, client Composer manifests

No existing client config, secrets, module activation, provider declaration, migration, or Composer lockfile is changed by this batch.