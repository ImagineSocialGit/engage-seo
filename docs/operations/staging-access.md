# Engage SEO staging browser access

This is a platform-owned staging-only browser gate for **all** web routes, including dynamically registered Forms feature routes. It does not create CRM users and requires no new migrations. It is inactive in local, testing, and production environments; `/staging-login` and `/staging-logout` respond 404 outside staging.

## Root-owned runtime configuration

Set `APP_ENV=staging` and supply **root/runtime-owned** `STAGING_USER` and `STAGING_PASSWORD_HASH`. The hash must be a PHP `password_hash()`-compatible bcrypt or Argon2 hash. Generate it outside the repository, install the complete encoded string (including `$` characters) without interpolation, and do not store credentials in the client repository or a selected-client `.env`. Legacy plaintext `STAGING_PASSWORD` is intentionally unsupported. `ClientEnvironmentLoader` allows only client-owned keys, and `ClientConfigLoader` rejects any client PHP config under the `staging` namespace.

Configure `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax` (or `strict`). Use an isolated session cookie name and correct `SESSION_DOMAIN` for this SEO staging host, distinct from Core staging/production; use `APP_DEBUG=false`, HTTPS, trusted proxies, and a persistent session store. With `APP_ENV=staging` the default secure-cookie flag is true; absent/invalid credentials or unsafe cookie settings cause an HTTP 503 (fail closed). Install credentials **before** generating Laravel cached config, and rebuild config cache after any rotation. Authentication only reads `config('staging.*')`; it never reads live `env()` or a client override.

On login the prior session ID is destroyed/regenerated, and only a keyed credential-version proof is stored. Credential or `APP_KEY` rotation invalidates existing access. Invalid logins use generic feedback. POST `/staging-login` requires CSRF and is throttled to five requests per minute under Laravel’s request-signature throttling. POST `/staging-logout` invalidates the session and regenerates its CSRF token.

## Route and indexing boundary

- Anonymous GET/HEAD of a protected web route redirects to `/staging-login`, storing only a safe relative path/query for return. Anonymous state-changing requests receive HTTP 403 rather than a redirect/replay (after any applicable CSRF enforcement). No feature route is implicitly exempt.
- The built-in `/up` endpoint is registered outside the web group and remains publicly checkable regardless of staging credential availability. It is **not** proof that staging access is configured.
- Public GET/HEAD `/robots.txt` remains accessible and returns `Disallow: /` in staging. `/sitemap.xml` is **not** exempt: anonymous requests redirect to login and authenticated staging requests return 404, even if a client mistakenly overrides `app.env` configuration. `X-Robots-Tag: noindex, nofollow, noarchive` and `Cache-Control: private, no-store, max-age=0` are attached to protected staging web responses.
- The dynamically registered POST `/_site-forms/{formKey}` route is protected by the same web middleware; the existing CSRF and form challenge checks remain in force **after** login. Static files served directly by Nginx are outside the PHP middleware, and must never contain preview secrets/private content.

## Acceptance and deployment handoff

Run the focused tests in the batch and verify under HTTPS, prior to making DNS public:

1. Anonymous `GET /up` => 200, `/robots.txt` => 200 containing `Disallow: /`, `/` => 302 to `/staging-login`, login GET => 200, all private responses have `no-store`/`noindex` headers. Anonymous `GET /sitemap.xml` => login redirect.
2. Anonymous POST `/_site-forms/example_form` with valid CSRF => 403. Login POST without CSRF => 419; repeated invalid attempts => 429. Do not print or log submitted credentials.
3. In a real HTTPS browser (or authenticated curl cookie jar + CSRF token), visit a protected path/query, sign in, confirm safe same-origin return and persistent access, confirm staging sitemap => 404, then POST logout with CSRF and confirm session access revoked.
4. Confirm production and local ordinary pages/forms retain existing behavior, and `/staging-login` is 404 on those environments. Confirm SEO/Core/staging/production session cookies remain isolated.

Infrastructure must install and attest the SEO commit, credentials, config cache, HTTPS, cookie policy, and staging gate before making `staging.hollyhatcheref.org` public. DNS, Nginx, Certbot, deployment provisioning, and HHEF client updates are deliberately outside this repository batch. Staging login does not replace TLS, application authorization, third-party callback signatures, or network hardening.