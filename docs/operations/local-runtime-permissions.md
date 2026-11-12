# Engage SEO local environment permission preparation

Engage SEO's platform-owned `scripts/create-client.sh` and
`scripts/bootstrap-client-local.sh` call
`scripts/operations/lib/client_environment_permissions.py` before performing
GitHub, database or application bootstrap work. This helper is **local only**.
It never reads or prints `.env` contents; it does not create a missing root
`.env`, install packages, rotate keys, or operate on remote hosts.

## Local contract

The developer owns the selected runtime files. The configured
`ENGAGE_SEO_WEB_GROUP` (default `www-data`) provides read/traverse access to
the `ENGAGE_SEO_WEB_USER` (default `www-data`).

- Root and selected-client `.env`: `0640`, developer:authorized runtime group.
- Selected-client directory: `2750`, developer:authorized runtime group.
- Newly created client `.env`: owned by the developer, `0640`, authorized group.
- No symlinks or multiply linked environment files; no recursive source changes.
- Root `.env` must already exist; the platform does not invent environment
  values or copy secrets from another installation.

An approved local create/bootstrap operation narrowly prepares these paths
and verifies service-user read/traverse access. Existing environment **values**
are not modified by the permissions helper. Repeated successful operations do
not reset permissions or rotate material. Changes to the selected root
`CLIENT_KEY` retain owner, group and mode and are checked afterward.

## Diagnose without changing permissions

```bash
cd /var/www/engage-seo
python3 scripts/operations/lib/client_environment_permissions.py verify-root \
  --root "$PWD" --runtime-user www-data --runtime-group www-data
python3 scripts/operations/lib/client_environment_permissions.py verify-client \
  --root "$PWD" --client holly-hatcher-ef-seo \
  --runtime-user www-data --runtime-group www-data
```

If root environment ownership or permissions need normalizing, the existing
approved local create/bootstrap commands do it before any database or remote
mutations. To explicitly prepare only that existing root environment, run
`prepare-root` instead of `verify-root`; this is a **mutating** command and
may require sudo for the exact group adjustment. Do not use chmod 777, run the
whole lifecycle as root, or change ownership recursively.

Infrastructure's universal inspector (`engage permissions inspect`) remains
read-only and reports POSIX metadata only. This SEO helper implements the
platform's local mutation and service-access boundary. It does **not** claim
that SEO staging/production remote deployment or Artist/Label adapters are
implemented. Those are independent lifecycle stages.