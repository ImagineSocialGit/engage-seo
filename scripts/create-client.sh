#!/usr/bin/env bash

set -euo pipefail

CLIENT_KEY="${1:-}"
CLIENT_TIMEZONE="${2:-}"
VERTICAL_KEY="${3:-}"
WEB_GROUP="${ENGAGE_SEO_WEB_GROUP:-www-data}"

if [[ -z "$CLIENT_KEY" || -z "$CLIENT_TIMEZONE" ]]; then
    echo "Usage: ./scripts/create-client.sh client-key timezone [vertical-key]"
    echo "Example: ./scripts/create-client.sh example-client America/Chicago"
    echo "Example: ./scripts/create-client.sh builder-co America/New_York construction"
    echo
    echo "Optional:"
    echo "  ENGAGE_SEO_WEB_GROUP=www-data"
    exit 1
fi

if [[ ! "$CLIENT_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]]; then
    echo "Client key must start with a lowercase letter or number and contain only lowercase letters, numbers, hyphens, and underscores."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "PHP is required."
    exit 1
fi

if ! getent group "$WEB_GROUP" >/dev/null 2>&1; then
    echo "Web server group does not exist: $WEB_GROUP"
    echo "Set ENGAGE_SEO_WEB_GROUP when the PHP-FPM group is not www-data."
    exit 1
fi

php -r '
$timezone = $argv[1] ?? "";

if (! in_array($timezone, timezone_identifiers_list(), true)) {
    fwrite(STDERR, "Invalid timezone: {$timezone}\n");
    exit(1);
}
' "$CLIENT_TIMEZONE"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLIENTS_DIR="$ROOT_DIR/clients"
CLIENT_DIR="$CLIENTS_DIR/$CLIENT_KEY"
TEMP_CLIENT_DIR=""

if [[ ! -f "$ROOT_DIR/artisan" || ! -f "$ROOT_DIR/config/verticals.php" ]]; then
    echo "Run this script from an Engage SEO checkout."
    exit 1
fi

if [[ -n "$VERTICAL_KEY" ]]; then
    php -r '
$config = require $argv[1];
$key = $argv[2] ?? "";
$available = $config["available"] ?? [];

if (! is_array($available) || ! array_key_exists($key, $available)) {
    fwrite(STDERR, "Unknown Engage SEO vertical: {$key}\n");
    exit(1);
}
' "$ROOT_DIR/config/verticals.php" "$VERTICAL_KEY"
fi

if [[ -e "$CLIENT_DIR" ]]; then
    echo "Client already exists: $CLIENT_DIR"
    exit 1
fi

mkdir -p "$CLIENTS_DIR"
TEMP_CLIENT_DIR="$(mktemp -d "$CLIENTS_DIR/.${CLIENT_KEY}.creating.XXXXXX")"

cleanup() {
    if [[ -n "$TEMP_CLIENT_DIR" && -d "$TEMP_CLIENT_DIR" ]]; then
        rm -rf "$TEMP_CLIENT_DIR"
    fi
}

trap cleanup EXIT

CLIENT_NAME="$(
    echo "$CLIENT_KEY" \
        | tr '_-' '  ' \
        | awk '{
            for (i = 1; i <= NF; i++) {
                $i = toupper(substr($i, 1, 1)) substr($i, 2)
            }
        } 1'
)"

VERTICAL_VALUE="null"

if [[ -n "$VERTICAL_KEY" ]]; then
    VERTICAL_VALUE="'$VERTICAL_KEY'"
fi

mkdir -p "$TEMP_CLIENT_DIR/config"
mkdir -p "$TEMP_CLIENT_DIR/resources/views"
mkdir -p "$TEMP_CLIENT_DIR/resources/images/raw"

cat > "$TEMP_CLIENT_DIR/config/client.php" <<EOF_CLIENT
<?php

return [
    'name' => '$CLIENT_NAME',
    'key' => '$CLIENT_KEY',
    'timezone' => '$CLIENT_TIMEZONE',
    'vertical' => $VERTICAL_VALUE,
];
EOF_CLIENT

cat > "$TEMP_CLIENT_DIR/config/features.php" <<'EOF_FEATURES'
<?php

return [
    'enabled' => [
    ],

    'disabled' => [
    ],
];
EOF_FEATURES

cat > "$TEMP_CLIENT_DIR/.env.example" <<'EOF_ENV'
# Engage SEO selected-client deployment environment
#
# Do not commit the real .env file.
#
# Root .env owns platform/process values such as:
#   APP_ENV / APP_DEBUG / APP_KEY
#   CLIENT_KEY
#   DB_CONNECTION / DB_HOST / DB_PORT
#   logging
#   queue/cache/session driver choices
#   shared process tuning
#
# This client .env owns values that vary with the selected website.

################################
# PUBLIC SITE
################################

APP_URL=

################################
# DATABASE IDENTITY / CREDENTIALS
################################

DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

################################
# CLIENT-SCOPED NAMESPACES
################################

CACHE_PREFIX=
REDIS_PREFIX=

# Optional when cookie domain must be explicit.
# SESSION_DOMAIN=.example.com

################################
# FILE STORAGE
################################

FILESYSTEM_DISK=local

# Add provider/storage credentials only when the selected client uses them.
EOF_ENV

cat > "$TEMP_CLIENT_DIR/.gitignore" <<'EOF_GITIGNORE'
/.env
.DS_Store
Thumbs.db
EOF_GITIGNORE

touch "$TEMP_CLIENT_DIR/resources/views/.gitkeep"
touch "$TEMP_CLIENT_DIR/resources/images/raw/.gitkeep"

cat > "$TEMP_CLIENT_DIR/README.md" <<EOF_README
# $CLIENT_NAME

Engage SEO client repository.

## Identity

- Client key: \`$CLIENT_KEY\`
- Timezone: \`$CLIENT_TIMEZONE\`
- Vertical: \`${VERTICAL_KEY:-none}\`

## Local setup

1. Create the client environment:

   \`\`\`bash
   sudo install -o "\$(id -un)" -g "$WEB_GROUP" -m 640 \\
     clients/$CLIENT_KEY/.env.example \\
     clients/$CLIENT_KEY/.env
   \`\`\`

2. Populate \`.env\` with the client URL, database identity/credentials, namespaces, and any client-specific provider values.

3. Set this in the Engage SEO platform root \`.env\`:

   \`\`\`env
   CLIENT_KEY=$CLIENT_KEY
   \`\`\`

4. Clear cached configuration:

   \`\`\`bash
   php artisan optimize:clear
   \`\`\`

5. Run migrations when the client database is ready:

   \`\`\`bash
   php artisan migrate
   \`\`\`

## Ownership

\`config/client.php\`
: Client identity, timezone, and optional vertical.

\`config/features.php\`
: Explicit Feature additions/disables.

\`resources/\`
: Client assets and future supported view/content overrides.

\`.env\`
: Deployment-specific client values and secrets; never commit it.

This repository should use documented Engage SEO override/integration seams. Do not duplicate platform or Engage Core responsibilities inside the client repository.
EOF_README

php -l "$TEMP_CLIENT_DIR/config/client.php" >/dev/null
php -l "$TEMP_CLIENT_DIR/config/features.php" >/dev/null

if ! chgrp -R "$WEB_GROUP" "$TEMP_CLIENT_DIR" 2>/dev/null; then
    if ! command -v sudo >/dev/null 2>&1; then
        echo "Unable to assign the client directory to group '$WEB_GROUP'."
        echo "Install sudo, run as root, or add the current user to that group."
        exit 1
    fi

    sudo chgrp -R "$WEB_GROUP" "$TEMP_CLIENT_DIR"
fi

find "$TEMP_CLIENT_DIR" -type d -exec chmod 2750 {} +
find "$TEMP_CLIENT_DIR" -type f -exec chmod 0640 {} +

mv "$TEMP_CLIENT_DIR" "$CLIENT_DIR"
TEMP_CLIENT_DIR=""
trap - EXIT

CURRENT_USER="$(id -un)"

cat <<EOF_DONE
Created Engage SEO client: $CLIENT_DIR
Name: $CLIENT_NAME
Timezone: $CLIENT_TIMEZONE
Vertical: ${VERTICAL_KEY:-none}
Permissions: directories 2750; files 0640; group $WEB_GROUP

Next:
  sudo install -o "$CURRENT_USER" -g "$WEB_GROUP" -m 640 \
    clients/$CLIENT_KEY/.env.example \
    clients/$CLIENT_KEY/.env

  # Populate clients/$CLIENT_KEY/.env
  # Set CLIENT_KEY=$CLIENT_KEY in the platform root .env

  php artisan optimize:clear
  php artisan migrate

Optional client repository initialization:
  cd clients/$CLIENT_KEY
  git init
EOF_DONE