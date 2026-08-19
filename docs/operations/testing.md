# Automated Testing Database

## Database engine

Engage SEO automated database tests use MySQL.

SQLite is not part of the platform testing contract. Database-backed Features
should be exercised against the same database engine used by deployed Engage SEO
sites so migration, indexing, foreign-key, query, and persistence behavior are
validated against MySQL.

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

## Client isolation

`phpunit.xml` forces:

```env
APP_ENV=testing
CLIENT_KEY=
DB_CONNECTION=mysql
```

The blank client key prevents a developer's currently selected client from being
loaded into the automated test application.

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
- `ENGAGE_SEO_TEST_DATABASE=true`.

The guard is intentionally separate from the local client reset/refresh safety
gate. A test database is platform testing infrastructure, not selected-client
runtime state.

## Running tests

Run tests as the normal shell/deploy user:

```bash
php artisan test
```

For a focused database-backed Feature:

```bash
php artisan test tests/Feature/Foundation/BlogFeatureFoundationTest.php
```

Tests may create, migrate, truncate, or drop Feature-owned test tables. The
configured MySQL testing database must therefore be disposable.