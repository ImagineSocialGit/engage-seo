#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

NON_INTERACTIVE=false

if [[ "${1:-}" == "--force" ]]; then
    NON_INTERACTIVE=true
elif [[ $# -gt 0 ]]; then
    echo "Usage: ./scripts/dev-reset-client-database.sh [--force]"
    exit 1
fi

if [[ ! -f artisan || ! -f bootstrap/app.php ]]; then
    echo "Run this script from an Engage SEO checkout."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "PHP is required."
    exit 1
fi

APP_ENV_VALUE="$(
    php artisan env --no-ansi 2>/dev/null \
        | awk -F'[][]' '/The application environment is/ { print $2; exit }'
)"

if [[ "$APP_ENV_VALUE" != "local" ]]; then
    echo "Refusing database reset: APP_ENV must resolve to local, got '$APP_ENV_VALUE'."
    exit 1
fi

php artisan optimize:clear >/dev/null

CONTEXT_OUTPUT="$(
    php artisan tinker --execute='
$connection = trim((string) config("database.default"));

fwrite(
    STDOUT,
    "__CLIENT_KEY__=" . trim((string) config("client.key")) . PHP_EOL
);

fwrite(
    STDOUT,
    "__DB_CONNECTION__=" . $connection . PHP_EOL
);

fwrite(
    STDOUT,
    "__DB_DATABASE__=" . trim(
        (string) config("database.connections." . $connection . ".database")
    ) . PHP_EOL
);

fwrite(
    STDOUT,
    "__DESTRUCTIVE_ENABLED__=" . (
        filter_var(
            env("DEV_DESTRUCTIVE_COMMANDS_ENABLED", false),
            FILTER_VALIDATE_BOOL
        ) ? "true" : "false"
    ) . PHP_EOL
);
' 2>/dev/null
)"

CLIENT_KEY_VALUE="$(
    printf '%s\n' "$CONTEXT_OUTPUT" \
        | sed -n 's/^__CLIENT_KEY__=//p' \
        | tail -n 1
)"

DB_CONNECTION_VALUE="$(
    printf '%s\n' "$CONTEXT_OUTPUT" \
        | sed -n 's/^__DB_CONNECTION__=//p' \
        | tail -n 1
)"

DB_DATABASE_VALUE="$(
    printf '%s\n' "$CONTEXT_OUTPUT" \
        | sed -n 's/^__DB_DATABASE__=//p' \
        | tail -n 1
)"

DESTRUCTIVE_ENABLED_VALUE="$(
    printf '%s\n' "$CONTEXT_OUTPUT" \
        | sed -n 's/^__DESTRUCTIVE_ENABLED__=//p' \
        | tail -n 1
)"

if [[ "$DESTRUCTIVE_ENABLED_VALUE" != "true" ]]; then
    echo "Refusing database reset: DEV_DESTRUCTIVE_COMMANDS_ENABLED must resolve to true."
    exit 1
fi

if [[ -z "$CLIENT_KEY_VALUE" ]]; then
    echo "Refusing database reset: no Engage SEO client is selected."
    exit 1
fi

if [[ ! -d "$ROOT_DIR/client/$CLIENT_KEY_VALUE" ]]; then
    echo "Refusing database reset: selected client directory does not exist."
    exit 1
fi

if [[ -z "$DB_CONNECTION_VALUE" ]]; then
    echo "Refusing database reset: the effective database connection is empty."
    exit 1
fi

if [[ -z "$DB_DATABASE_VALUE" ]]; then
    echo "Refusing database reset: the effective database name is empty."
    exit 1
fi

echo "Engage SEO local client reset"
echo "  Environment: $APP_ENV_VALUE"
echo "  Client:      $CLIENT_KEY_VALUE"
echo "  Connection:  $DB_CONNECTION_VALUE"
echo "  Database:    $DB_DATABASE_VALUE"
echo
echo "This drops every table, view, and Laravel migration-history row in the selected database."
echo "It does not drop/recreate the database itself."

if [[ "$NON_INTERACTIVE" != true ]]; then
    read -r -p "Type RESET $CLIENT_KEY_VALUE to continue: " CONFIRMATION

    if [[ "$CONFIRMATION" != "RESET $CLIENT_KEY_VALUE" ]]; then
        echo "Reset cancelled."
        exit 1
    fi
fi

php artisan db:wipe --force

echo
echo "Selected client database is empty."