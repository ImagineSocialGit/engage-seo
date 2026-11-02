#!/usr/bin/env bash

set -Eeuo pipefail

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/create-client.sh client-key timezone [vertical-key] [options]

Options:
  --name "Client Name"    Use the actual client organization name.
  --vertical KEY          Equivalent to the optional positional vertical-key.
  -h, --help              Show this help.

Environment overrides:
  ENGAGE_SEO_WEB_GROUP=www-data
  ENGAGE_SEO_GITHUB_OWNER=ImagineSocialGit

Creates a separate private GitHub client repository and provisions the local
client database and runtime. Never creates or edits staging/production state.
On partial failure, re-run only the recovery command printed by the script.

Example:
  ./scripts/create-client.sh holly-hatcher-ef-seo America/Chicago \
    --name "Holly Hatcher Education Foundation"
USAGE
}

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

CLIENT_KEY=""
CLIENT_TIMEZONE=""
CLIENT_NAME=""
VERTICAL_KEY=""
WEB_GROUP="${ENGAGE_SEO_WEB_GROUP:-www-data}"
GITHUB_OWNER="${ENGAGE_SEO_GITHUB_OWNER:-ImagineSocialGit}"

while (($#)); do
    case "$1" in
        --name)
            [[ $# -ge 2 ]] || fail '--name requires a value.'
            CLIENT_NAME="$2"
            shift 2
            ;;
        --vertical)
            [[ $# -ge 2 ]] || fail '--vertical requires a value.'
            if [[ -n "$VERTICAL_KEY" ]]; then
                fail 'Vertical was specified more than once.'
            fi
            VERTICAL_KEY="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        --*) fail "Unknown option: $1" ;;
        *)
            if [[ -z "$CLIENT_KEY" ]]; then
                CLIENT_KEY="$1"
            elif [[ -z "$CLIENT_TIMEZONE" ]]; then
                CLIENT_TIMEZONE="$1"
            elif [[ -z "$VERTICAL_KEY" ]]; then
                VERTICAL_KEY="$1"
            else
                fail "Unexpected positional argument: $1"
            fi
            shift
            ;;
    esac
done

[[ -n "$CLIENT_KEY" && -n "$CLIENT_TIMEZONE" ]] || { usage >&2; exit 2; }
[[ "$CLIENT_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || fail 'Invalid client key.'
[[ -z "$VERTICAL_KEY" || "$VERTICAL_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]] \
    || fail 'Invalid vertical key.'
[[ "$GITHUB_OWNER" =~ ^[A-Za-z0-9][A-Za-z0-9-]*$ ]] || fail 'Invalid GitHub owner.'
[[ -z "$CLIENT_NAME" || ( "$CLIENT_NAME" != *$'\n'* && "$CLIENT_NAME" != *$'\r'* ) ]] \
    || fail 'Client name must be a single line.'

for executable in php git gh getent; do
    command -v "$executable" >/dev/null 2>&1 || fail "Missing command: $executable"
done
getent group "$WEB_GROUP" >/dev/null 2>&1 || fail "Missing PHP-FPM group: $WEB_GROUP"
php -r '$t=$argv[1]; if (!in_array($t, timezone_identifiers_list(), true)) { fwrite(STDERR,"Invalid timezone\n"); exit(1); }' "$CLIENT_TIMEZONE"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLIENT_ROOT="$ROOT_DIR/client"
CLIENT_DIR="$CLIENT_ROOT/$CLIENT_KEY"
TEMP_CLIENT_DIR=""
GITHUB_REPOSITORY="$GITHUB_OWNER/$CLIENT_KEY"
GITHUB_SSH_URL="git@github.com:$GITHUB_REPOSITORY.git"
LOCAL_BOOTSTRAP="$ROOT_DIR/scripts/bootstrap-client-local.sh"

[[ -f "$ROOT_DIR/artisan" && -f "$ROOT_DIR/config/verticals.php" ]] \
    || fail 'Run from an Engage SEO checkout.'
[[ -f "$ROOT_DIR/vendor/autoload.php" && -f "$ROOT_DIR/.env" ]] \
    || fail 'Install root PHP dependencies and create local root .env first.'
[[ -f "$LOCAL_BOOTSTRAP" ]] || fail 'Local bootstrap helper is missing.'
[[ ! -e "$CLIENT_DIR" && ! -L "$CLIENT_DIR" ]] || fail "Client already exists: $CLIENT_DIR"

# Refuse external repository writes unless this checkout is a local dev environment.
php -r '
require $argv[1]."/vendor/autoload.php";
$env = Dotenv\Dotenv::createArrayBacked($argv[1], ".env")->safeLoad();
if (($env["APP_ENV"] ?? null) !== "local") {
    fwrite(STDERR, "Client creation requires root APP_ENV=local.\n");
    exit(1);
}
' "$ROOT_DIR"

if [[ -n "$VERTICAL_KEY" ]]; then
    php -r '
    $config = require $argv[1];
    $key = $argv[2];
    if (!isset($config["available"][$key])) {
        fwrite(STDERR, "Unknown Engage SEO vertical: {$key}\n");
        exit(1);
    }
    ' "$ROOT_DIR/config/verticals.php" "$VERTICAL_KEY"
fi

gh auth status --hostname github.com >/dev/null 2>&1 \
    || fail 'GitHub CLI not authenticated. Run gh auth login.'
if gh repo view "$GITHUB_REPOSITORY" --json nameWithOwner >/dev/null 2>&1; then
    fail "Remote repository already exists: $GITHUB_REPOSITORY"
fi
GIT_AUTHOR_NAME="$(git -C "$ROOT_DIR" config user.name 2>/dev/null || true)"
GIT_AUTHOR_EMAIL="$(git -C "$ROOT_DIR" config user.email 2>/dev/null || true)"
[[ -n "$GIT_AUTHOR_NAME" && -n "$GIT_AUTHOR_EMAIL" ]] \
    || fail 'Git user.name and user.email must be configured.'

mkdir -p "$CLIENT_ROOT"
TEMP_CLIENT_DIR="$(mktemp -d "$CLIENT_ROOT/.${CLIENT_KEY}.creating.XXXXXX")"
cleanup() {
    if [[ -n "$TEMP_CLIENT_DIR" && -d "$TEMP_CLIENT_DIR" ]]; then
        rm -rf -- "$TEMP_CLIENT_DIR"
    fi
}
trap cleanup EXIT

if [[ -z "$CLIENT_NAME" ]]; then
    CLIENT_NAME="$(
        echo "$CLIENT_KEY" | tr '_-' '  ' | awk '{
            for (i = 1; i <= NF; i++) {
                $i = toupper(substr($i, 1, 1)) substr($i, 2)
            }
        } 1'
    )"
fi
CLIENT_NAME_PHP="$(php -r 'echo var_export($argv[1], true);' "$CLIENT_NAME")"
VERTICAL_VALUE="null"
if [[ -n "$VERTICAL_KEY" ]]; then
    VERTICAL_VALUE="$(php -r 'echo var_export($argv[1], true);' "$VERTICAL_KEY")"
fi
mkdir -p "$TEMP_CLIENT_DIR/bootstrap"
mkdir -p "$TEMP_CLIENT_DIR/config/pages"
mkdir -p "$TEMP_CLIENT_DIR/resources/views/pages"
mkdir -p "$TEMP_CLIENT_DIR/resources/views/sections"
mkdir -p "$TEMP_CLIENT_DIR/resources/images/raw"
mkdir -p "$TEMP_CLIENT_DIR/resources/migration"

cat > "$TEMP_CLIENT_DIR/config/client.php" <<EOF_CLIENT
<?php

return [
    'name' => $CLIENT_NAME_PHP,
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

cat > "$TEMP_CLIENT_DIR/config/seo_migration.php" <<'EOF_SEO_MIGRATION'
<?php

return [
    'enabled' => false,
    'inventory_path' => 'resources/migration/legacy-urls.tsv',
];
EOF_SEO_MIGRATION

cat > "$TEMP_CLIENT_DIR/config/site.php" <<EOF_SITE
<?php

return [
    'name' => $CLIENT_NAME_PHP,

    'brand' => [
        'logo' => null,
        'logo_alt' => null,
    ],

    'business' => [
        'phone' => null,
        'email' => null,
        'address' => [
            'lines' => [],
            'url' => null,
            'new_tab' => false,
        ],
        'social_links' => [],
    ],

    'seo' => [
        // Enable only when the production site is ready to be indexed.
        'indexing_enabled' => false,

        'redirects' => [
        ],
    ],

    'shell' => [
        'utility_bar' => [
            'enabled' => false,
            'theme' => 'inverse',
            'items' => [
            ],
        ],

        'header' => [
            'enabled' => true,
            'theme' => 'default',
        ],

        'navigation' => [
            'items' => [
            ],

            'primary_cta' => null,
        ],

        'footer' => [
            'theme' => 'default',
            'intro' => null,
            'groups' => [
            ],
            'cta' => null,
            'legal' => [
                'lines' => [
                ],
                'links' => [
                ],
            ],
        ],
    ],
];
EOF_SITE

cat > "$TEMP_CLIENT_DIR/.env.example" <<'EOF_ENV'
# Engage SEO selected-client deployment environment
#
# This file contains runtime values that should follow the selected CLIENT_KEY.
# Do not commit the real .env file.
#
# Staging and production each create their own real .env from this template.
# Never promote/copy a populated .env between environments. Future external
# integration destinations and credentials are runtime-specific too.
#
# Root .env owns:
#   CLIENT_KEY
#   APP_ENV / APP_DEBUG / APP_KEY
#   DB_CONNECTION / DB_HOST / DB_PORT
#   logging
#   cache/session/queue driver choices
#   Redis host/port/database indexes
#   filesystem transport choice
#   destructive-local-operation gate
#
# Client PHP config owns:
#   client name/key
#   stable client timezone
#   optional vertical
#   enabled/disabled Features
#   public site identity, business/contact data, shell, navigation, footer, and theme overrides
#   public static page definitions

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

# Keep these unique per client/environment when infrastructure is shared.
CACHE_PREFIX=
REDIS_PREFIX=

# Optional when the session cookie domain must be explicit.
# SESSION_DOMAIN=.example.com

# Add new client-owned environment keys only when Engage SEO documents and
# validates the corresponding platform/integration seam.
EOF_ENV

cat > "$TEMP_CLIENT_DIR/.gitignore" <<'EOF_GITIGNORE'
/.env
/.env.*
!/.env.example
.DS_Store
Thumbs.db
EOF_GITIGNORE

printf 'path\toutcome\ttarget\tnotes\n' > "$TEMP_CLIENT_DIR/resources/migration/legacy-urls.tsv"

touch "$TEMP_CLIENT_DIR/bootstrap/.gitkeep"
touch "$TEMP_CLIENT_DIR/config/pages/.gitkeep"
touch "$TEMP_CLIENT_DIR/resources/views/pages/.gitkeep"
touch "$TEMP_CLIENT_DIR/resources/views/sections/.gitkeep"
touch "$TEMP_CLIENT_DIR/resources/images/raw/.gitkeep"

cat > "$TEMP_CLIENT_DIR/README.md" <<EOF_README
# $CLIENT_NAME

Engage SEO client repository: \`$GITHUB_REPOSITORY\`

## Identity

- Client key: \`$CLIENT_KEY\`
- Timezone: \`$CLIENT_TIMEZONE\`
- Vertical: \`${VERTICAL_KEY:-none}\`

## Private repository

\`$GITHUB_SSH_URL\`

## Deployment environments

The same client repository may be deployed to staging and production, but each deployment creates and owns its own real \`.env\`.

Standard public topology:

\`\`\`text
staging Engage SEO Sites:     staging.domain.com
production Engage SEO Sites:  domain.com
\`\`\`

When an Engage Core integration exists, staging Engage SEO Sites must use only staging Core and production Engage SEO Sites must use only production Core. Integration destinations and credentials are runtime-specific and must never be promoted as client content/configuration state.

The current SEO platform does not yet define Core API destination or credential variables; do not invent them from CRM hostnames. Follow the platform's current environment-pairing documentation when that integration seam is added.

## Local setup

The client creation workflow initializes the private repository, generates the
local client environment, provisions an isolated MySQL database, migrates and
bootstraps the selected client, validates configuration, and selects it locally.
If local bootstrap failed after repository creation, resume without recreating
source or GitHub:

\`\`\`bash
./scripts/bootstrap-client-local.sh $CLIENT_KEY
\`\`\`

The same source repository is deployed separately to staging and production.
Runtime credentials and selected-client environments remain uncommitted.

## Ownership

\`bootstrap/*.php\`
: Optional source-controlled one-time initial state for registered bootstrap contributors.

\`config/client.php\`
: Client identity, timezone, and optional vertical.

\`config/features.php\`
: Explicit Feature additions/disables.

\`config/seo_migration.php\`
: Optional old-platform migration audit enablement and inventory path.

\`config/site.php\`
: Public site name, brand assets, business/contact data, utility/header/navigation/footer configuration, and optional semantic theme-token overrides.

\`config/pages/*.php\`
: Static public SEO/business page definitions.

\`resources/views/pages/public.blade.php\`
: Optional override of the platform public page shell.

\`resources/views/sections/*.blade.php\`
: Optional overrides of registered reusable platform section views.

\`resources/images/raw/\`
: Client-owned raw source imagery.

\`resources/migration/legacy-urls.tsv\`
: Source-controlled legacy URL inventory used when replacing an existing public site.

\`.env\`
: Deployment-specific client values and secrets; never commit it and never copy a populated file between staging and production.

For staging/production environment ownership and Engage Core pairing rules, follow the platform document:

\`\`\`text
docs/operations/environment-pairing.md
\`\`\`

When an enabled Feature stores editorial content in the database, do not copy the staging database into production. Promote reviewed editorial state through:

\`\`\`text
docs/operations/editorial-promotion.md
\`\`\`

This repository should use documented Engage SEO override/integration seams. Do not duplicate platform or Engage Core responsibilities inside the client repository.
EOF_README

php -l "$TEMP_CLIENT_DIR/config/client.php" >/dev/null
php -l "$TEMP_CLIENT_DIR/config/features.php" >/dev/null
php -l "$TEMP_CLIENT_DIR/config/seo_migration.php" >/dev/null
php -l "$TEMP_CLIENT_DIR/config/site.php" >/dev/null

if ! chgrp -R "$WEB_GROUP" "$TEMP_CLIENT_DIR" 2>/dev/null; then
    command -v sudo >/dev/null 2>&1 || fail 'sudo is required to set client filesystem group.'
    sudo chgrp -R "$WEB_GROUP" "$TEMP_CLIENT_DIR"
fi
find "$TEMP_CLIENT_DIR" -type d -exec chmod 2750 {} +
find "$TEMP_CLIENT_DIR" -type f -exec chmod 0640 {} +

# Commit while the scaffold is still unpublished. A remote-creation failure
# therefore cannot leave a half-created client at client/<key>.
git -C "$TEMP_CLIENT_DIR" init -q
git -C "$TEMP_CLIENT_DIR" config user.name "$GIT_AUTHOR_NAME"
git -C "$TEMP_CLIENT_DIR" config user.email "$GIT_AUTHOR_EMAIL"
git -C "$TEMP_CLIENT_DIR" add .
git -C "$TEMP_CLIENT_DIR" commit -q -m 'feat: initialize SEO client'
git -C "$TEMP_CLIENT_DIR" branch -M main

printf 'Creating private GitHub repository: %s\n' "$GITHUB_REPOSITORY"
gh repo create "$GITHUB_REPOSITORY" --private \
    || fail 'GitHub repository creation failed. No client scaffold was published.'
git -C "$TEMP_CLIENT_DIR" remote add origin "$GITHUB_SSH_URL"

if ! git -C "$TEMP_CLIENT_DIR" push -u origin main; then
    # Do not delete the newly created remote: keep local source with the matching
    # commit intact and give an exact retry command.
    mv -- "$TEMP_CLIENT_DIR" "$CLIENT_DIR"
    TEMP_CLIENT_DIR=""
    trap - EXIT
    printf 'Repository created but initial push failed. Local source preserved: %s\n' "$CLIENT_DIR" >&2
    printf 'Retry: git -C client/%s push -u origin main\n' "$CLIENT_KEY" >&2
    exit 1
fi

mv -- "$TEMP_CLIENT_DIR" "$CLIENT_DIR"
TEMP_CLIENT_DIR=""
trap - EXIT

if ! bash "$LOCAL_BOOTSTRAP" "$CLIENT_KEY"; then
    printf '\nClient source and private GitHub repository remain intact.\n' >&2
    printf 'After correcting the local setup issue, retry:\n' >&2
    printf '  ./scripts/bootstrap-client-local.sh %s\n' "$CLIENT_KEY" >&2
    exit 1
fi

cat <<EOF_DONE
Created Engage SEO client: $CLIENT_DIR
Name: $CLIENT_NAME
Timezone: $CLIENT_TIMEZONE
Vertical: ${VERTICAL_KEY:-none}
Repository: $GITHUB_SSH_URL
Branch: main (private)
Permissions: directories 2750; files 0640; group $WEB_GROUP
Local bootstrap: ready

Next: configure branding, public pages, media, and SEO launch state.
EOF_DONE