# Automated Testing Database

## Database engine

Engage SEO automated database tests use MySQL.

SQLite is not part of the platform testing contract. Database-backed Features
should be exercised against the same database engine used by deployed Engage SEO
sites so migration, indexing, foreign-key, query, and persistence behavior are
validated against MySQL. Platform database/queue fallback defaults also resolve to
MySQL rather than silently selecting SQLite when an environment value is absent.

## Testing environment

Laravel's dedicated testing environment file is:

```text
.env.testing
```

Create it from the committed template:

```bash
cp .env.testing.example .env.testing
```

Populate only a dedicated disposable MySQL testing database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
ENGAGE_SEO_TEST_DATABASE=true
```

Do not point `.env.testing` at:

- a selected client's local working database;
- a staging database;
- a production database;
- an Engage Core database.

The real `.env.testing` is runtime state and is ignored by Git.

## Process isolation

`php artisan test` first boots an Artisan process before Laravel launches PHPUnit.
A selected client can legitimately place client-owned values such as `DB_DATABASE`
into that parent process environment. Child processes inherit process environment
values, so PHPUnit must sanitize that state before the test application boots.

`phpunit.xml` therefore uses:

```text
tests/bootstrap.php
```

as its bootstrap. The testing bootstrap:

1. loads Composer;
2. clears the platform's generic selected-client-owned environment keys;
3. clears the database-test safety guard and prior bootstrap database sentinel;
4. loads `.env.testing`;
5. forces `APP_ENV=testing` and `CLIENT_KEY=` after loading;
6. records the non-blank `DB_DATABASE` that came specifically from
   `.env.testing`.

This makes both direct PHPUnit execution and `php artisan test` converge on the
same dedicated testing environment instead of trusting environment inherited from
an already-booted selected-client Artisan process.

`phpunit.xml` additionally forces:

```env
APP_ENV=testing
CLIENT_KEY=
DB_CONNECTION=mysql
```

The blank client key prevents a selected client from being loaded into the
automated test application. The bootstrap's explicit sanitization is still
required because client-owned variables may already have been exported by the
parent Artisan process before PHPUnit starts.

## Database guard

Tests that access the database extend:

```text
Tests\DatabaseTestCase
```

That base class refuses to run unless:

- Laravel resolves `APP_ENV=testing`;
- a root `.env.testing` file exists;
- no client is selected;
- the effective database connection is MySQL;
- the effective MySQL database name is non-blank;
- the effective MySQL database name exactly matches the database name loaded by
  the PHPUnit bootstrap from `.env.testing`;
- `ENGAGE_SEO_TEST_DATABASE=true`.

The guard is intentionally separate from the local client reset/refresh safety
gate. A test database is platform testing infrastructure, not selected-client
runtime state.

If `.env.testing` is absent or does not provide a non-blank `DB_DATABASE`, the
bootstrap removes inherited selected-client database values and the database guard
fails closed before a database-backed test may manipulate schema.

## Running tests

Run tests as the normal shell/deploy user:

```bash
php artisan test
```

For a focused database-backed Feature:

```bash
php artisan test tests/Feature/Foundation/BlogFeatureFoundationTest.php
```

Direct PHPUnit execution remains supported:

```bash
vendor/bin/phpunit tests/Feature/Foundation/BlogFeatureFoundationTest.php
```

Tests may create, migrate, truncate, or drop Feature-owned test tables. The
configured MySQL testing database must therefore be disposable.