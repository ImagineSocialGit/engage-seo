#!/usr/bin/env bash
set -Eeuo pipefail

# Generic Client SEO Site state dump
#
# Place this script in <project>/scripts/ and run it from anywhere.
# By default, the project root is the script directory's parent.
# You may also pass an explicit project root as the first argument.
#
# Output:
#   <project>/file_dumps/
#     00-project-overview.txt
#     01-app-backend.txt
#     02-config-routes-bootstrap.txt
#     03-database.txt
#     04-resources-frontend.txt
#     05-public-scripts.txt
#     06-tests-docs-root.txt
#     07-runtime-routing.txt
#     08-git-state.txt
#     manifest.tsv
#
# Intentionally excluded:
# - .env files and private keys/certificates
# - vendor/, node_modules/, storage/, bootstrap/cache/
# - compiled frontend output
# - binary/media files
# - database.sqlite and other database binaries
# - dependency lock files from content dumps
#
# The goal is to capture enough source/config/runtime structure to analyze:
# - reusable platform architecture
# - client-specific content and branding
# - CMS/page composition
# - SEO/public routing
# - accessibility-relevant Blade markup
# - Engage Core integration seams
# - tests, docs, scripts, and Git state

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="${1:-$(cd -- "${SCRIPT_DIR}/.." && pwd)}"
ROOT_DIR="$(cd -- "${ROOT_DIR}" && pwd)"
OUTPUT_DIR="${ROOT_DIR}/file_dumps"

if [[ ! -f "${ROOT_DIR}/artisan" || ! -f "${ROOT_DIR}/composer.json" ]]; then
    printf 'Error: %s does not look like the Laravel project root.\n' "${ROOT_DIR}" >&2
    printf 'Usage: %s [project-root]\n' "$0" >&2
    exit 1
fi

mkdir -p "${OUTPUT_DIR}"

rm -f -- \
    "${OUTPUT_DIR}/00-project-overview.txt" \
    "${OUTPUT_DIR}/01-app-backend.txt" \
    "${OUTPUT_DIR}/02-config-routes-bootstrap.txt" \
    "${OUTPUT_DIR}/03-database.txt" \
    "${OUTPUT_DIR}/04-resources-frontend.txt" \
    "${OUTPUT_DIR}/05-public-scripts.txt" \
    "${OUTPUT_DIR}/06-tests-docs-root.txt" \
    "${OUTPUT_DIR}/07-runtime-routing.txt" \
    "${OUTPUT_DIR}/08-git-state.txt" \
    "${OUTPUT_DIR}/manifest.tsv"

relative_path() {
    local path="$1"
    printf '%s\n' "${path#"${ROOT_DIR}/"}"
}

is_allowed_text_file() {
    local file="$1"

    case "$file" in
        *.php|*.blade.php|*.js|*.mjs|*.cjs|*.ts|*.tsx|*.css|*.scss|*.json|*.md|*.txt|*.xml|*.yml|*.yaml|*.toml|*.ini|*.conf|*.stub|*.example|*.sh)
            return 0
            ;;
        */artisan|*/vite.config.js|*/phpunit.xml|*/robots.txt)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

is_excluded_path() {
    local rel="$1"

    case "$rel" in
        .git/*|vendor/*|node_modules/*|storage/*|bootstrap/cache/*|public/build/*|public/hot|file_dumps/*)
            return 0
            ;;
        .env|.env.*|*.key|*.pem|*.crt|*.p12|*.pfx)
            return 0
            ;;
        composer.lock|package-lock.json|pnpm-lock.yaml|yarn.lock)
            return 0
            ;;
        database/*.sqlite|database/*.sqlite3|database/*.db)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

append_manifest_row() {
    local file="$1"
    local rel bytes lines hash

    rel="$(relative_path "$file")"
    bytes="$(wc -c < "$file" | tr -d ' ')"
    lines="$(wc -l < "$file" | tr -d ' ')"
    hash="$(sha256sum "$file" | awk '{print $1}')"

    printf '%s\t%s\t%s\t%s\n' \
        "$rel" \
        "$bytes" \
        "$lines" \
        "$hash" \
        >> "${OUTPUT_DIR}/manifest.tsv"
}

append_file() {
    local output="$1"
    local file="$2"
    local rel

    rel="$(relative_path "$file")"

    is_excluded_path "$rel" && return 0
    is_allowed_text_file "$file" || return 0

    {
        printf '\n===== BEGIN FILE: %s =====\n\n' "$rel"
        cat -- "$file"
        printf '\n\n===== END FILE: %s =====\n' "$rel"
    } >> "$output"

    append_manifest_row "$file"
}

write_dump() {
    local output_name="$1"
    shift

    local output="${OUTPUT_DIR}/${output_name}"
    local path file

    : > "$output"

    {
        printf 'Generic Client SEO Site File Dump\n'
        printf 'Generated: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
        printf 'Project root: %s\n' "$ROOT_DIR"
        printf 'Dump: %s\n' "$output_name"
    } >> "$output"

    for path in "$@"; do
        [[ -e "$path" ]] || continue

        if [[ -f "$path" ]]; then
            append_file "$output" "$path"
            continue
        fi

        while IFS= read -r -d '' file; do
            append_file "$output" "$file"
        done < <(find "$path" -type f -print0 | sort -z)
    done
}

count_files() {
    local directory="$1"

    if [[ -d "${ROOT_DIR}/${directory}" ]]; then
        find "${ROOT_DIR}/${directory}" \
            -type f \
            ! -path '*/vendor/*' \
            ! -path '*/node_modules/*' \
            ! -path '*/storage/*' \
            ! -path '*/bootstrap/cache/*' \
            ! -path '*/public/build/*' \
            ! -path '*/file_dumps/*' \
            | wc -l \
            | tr -d ' '
    else
        printf '0'
    fi
}

printf 'path\tbytes\tlines\tsha256\n' > "${OUTPUT_DIR}/manifest.tsv"

# ---------------------------------------------------------------------------
# 00 - High-level architecture/dependency overview
# ---------------------------------------------------------------------------

{
    printf 'Generic Client SEO Site Project Overview\n'
    printf 'Generated: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    printf 'Project root: %s\n\n' "$ROOT_DIR"

    printf '== Runtime versions ==\n'
    php -v 2>/dev/null | head -n 2 || true
    composer --version 2>/dev/null || true
    node --version 2>/dev/null || true
    npm --version 2>/dev/null || true

    printf '\n== Laravel ==\n'
    (
        cd "$ROOT_DIR"
        php artisan --version 2>/dev/null || true
    )

    printf '\n== Top-level files and directories ==\n'
    find "$ROOT_DIR" \
        -mindepth 1 \
        -maxdepth 1 \
        ! -name '.git' \
        ! -name 'vendor' \
        ! -name 'node_modules' \
        ! -name 'storage' \
        ! -name 'file_dumps' \
        -printf '%f\n' \
        | sort

    printf '\n== Source file counts ==\n'
    for directory in app config database resources routes tests docs public scripts; do
        if [[ -d "${ROOT_DIR}/${directory}" ]]; then
            printf '%-12s %s\n' "$directory" "$(count_files "$directory")"
        fi
    done

    printf '\n== Application source structure ==\n'
    for directory in app config resources/views resources/js resources/css routes scripts tests docs; do
        if [[ -d "${ROOT_DIR}/${directory}" ]]; then
            printf '\n-- %s --\n' "$directory"
            (
                cd "$ROOT_DIR"
                find "$directory" \
                    -type f \
                    ! -path '*/vendor/*' \
                    ! -path '*/node_modules/*' \
                    ! -path '*/storage/*' \
                    ! -path '*/bootstrap/cache/*' \
                    ! -path '*/public/build/*' \
                    | sort
            )
        fi
    done

    printf '\n== Page configuration files ==\n'
    if [[ -d "${ROOT_DIR}/config/pages" ]]; then
        (
            cd "$ROOT_DIR"
            find config/pages -maxdepth 1 -type f -printf '%p\n' | sort
        )
    else
        printf 'No config/pages directory.\n'
    fi

    printf '\n== Site theme configuration files ==\n'
    if [[ -d "${ROOT_DIR}/config/site_theme" ]]; then
        (
            cd "$ROOT_DIR"
            find config/site_theme -type f -printf '%p\n' | sort
        )
    else
        printf 'No config/site_theme directory.\n'
    fi

    printf '\n== Blade page/layout/component inventory ==\n'
    if [[ -d "${ROOT_DIR}/resources/views" ]]; then
        (
            cd "$ROOT_DIR"
            find resources/views -type f \
                \( -name '*.blade.php' -o -name '*.php' \) \
                -printf '%p\n' \
                | sort
        )
    fi

    printf '\n== Public text/config files ==\n'
    if [[ -d "${ROOT_DIR}/public" ]]; then
        (
            cd "$ROOT_DIR"
            find public -type f \
                \( \
                    -name 'robots.txt' -o \
                    -name '*.xml' -o \
                    -name '*.json' -o \
                    -name '*.txt' -o \
                    -name '*.webmanifest' \
                \) \
                ! -path 'public/build/*' \
                -printf '%p\n' \
                | sort
        )
    fi

    printf '\n== Composer package summary ==\n'
    if command -v composer >/dev/null 2>&1; then
        (
            cd "$ROOT_DIR"
            composer show --locked --direct --no-interaction 2>/dev/null
        ) || true
    fi

    printf '\n== NPM package summary ==\n'
    if command -v npm >/dev/null 2>&1; then
        (
            cd "$ROOT_DIR"
            npm ls --depth=0 2>/dev/null
        ) || true
    fi
} > "${OUTPUT_DIR}/00-project-overview.txt"

# ---------------------------------------------------------------------------
# 01 - Backend application code
# ---------------------------------------------------------------------------

write_dump "01-app-backend.txt" \
    "${ROOT_DIR}/app"

# ---------------------------------------------------------------------------
# 02 - Configuration, routes, bootstrap, platform composition
# ---------------------------------------------------------------------------

write_dump "02-config-routes-bootstrap.txt" \
    "${ROOT_DIR}/config" \
    "${ROOT_DIR}/routes" \
    "${ROOT_DIR}/bootstrap/app.php" \
    "${ROOT_DIR}/bootstrap/providers.php"

# ---------------------------------------------------------------------------
# 03 - Database schema/factories/seeders
# ---------------------------------------------------------------------------

write_dump "03-database.txt" \
    "${ROOT_DIR}/database"

# ---------------------------------------------------------------------------
# 04 - Blade, CSS, JS, raw frontend source
# ---------------------------------------------------------------------------

write_dump "04-resources-frontend.txt" \
    "${ROOT_DIR}/resources"

# ---------------------------------------------------------------------------
# 05 - Public SEO files and project-maintenance scripts
# ---------------------------------------------------------------------------

write_dump "05-public-scripts.txt" \
    "${ROOT_DIR}/public/robots.txt" \
    "${ROOT_DIR}/public/sitemap.xml" \
    "${ROOT_DIR}/public/manifest.json" \
    "${ROOT_DIR}/public/site.webmanifest" \
    "${ROOT_DIR}/scripts"

# ---------------------------------------------------------------------------
# 06 - Tests, docs, and root project configuration
# ---------------------------------------------------------------------------

write_dump "06-tests-docs-root.txt" \
    "${ROOT_DIR}/tests" \
    "${ROOT_DIR}/docs" \
    "${ROOT_DIR}/README.md" \
    "${ROOT_DIR}/composer.json" \
    "${ROOT_DIR}/package.json" \
    "${ROOT_DIR}/phpunit.xml" \
    "${ROOT_DIR}/vite.config.js"

# ---------------------------------------------------------------------------
# 07 - Runtime/routing snapshot
#
# This intentionally captures commands useful for reconstructing how the
# application behaves without exporting secrets or database contents.
# ---------------------------------------------------------------------------

{
    printf 'Generic Client SEO Site Runtime / Routing Snapshot\n'
    printf 'Generated: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    printf 'Project root: %s\n\n' "$ROOT_DIR"

    cd "$ROOT_DIR"

    printf '== Artisan about ==\n'
    php artisan about --no-ansi 2>&1 || true

    printf '\n== Route list ==\n'
    php artisan route:list --no-ansi 2>&1 || true

    printf '\n== Migration status ==\n'
    php artisan migrate:status --no-ansi 2>&1 || true

    printf '\n== Registered console commands relevant to site operations ==\n'
    php artisan list --no-ansi 2>/dev/null \
        | grep -Ei '(^|[[:space:]])(about|route|view|cache|config|migrate|db|storage|schedule|queue|site|page|image|post|seo|sitemap)' \
        || true
} > "${OUTPUT_DIR}/07-runtime-routing.txt"

# ---------------------------------------------------------------------------
# 08 - Git state
# ---------------------------------------------------------------------------

{
    printf 'Generic Client SEO Site Git State\n'
    printf 'Generated: %s\n' "$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    printf 'Project root: %s\n\n' "$ROOT_DIR"

    if git -C "$ROOT_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        printf '== Branch ==\n'
        git -C "$ROOT_DIR" branch --show-current || true

        printf '\n== Status ==\n'
        git -C "$ROOT_DIR" status --short --branch || true

        printf '\n== Recent commits ==\n'
        git -C "$ROOT_DIR" log \
            -n 25 \
            --date=iso \
            --pretty=format:'%h %ad %an %s' \
            || true

        printf '\n\n== Changed files ==\n'
        git -C "$ROOT_DIR" diff --name-status || true

        printf '\n== Staged files ==\n'
        git -C "$ROOT_DIR" diff --cached --name-status || true

        printf '\n== Diff statistics ==\n'
        git -C "$ROOT_DIR" diff --stat || true
        git -C "$ROOT_DIR" diff --cached --stat || true

        printf '\n== Tracked files relevant to site architecture ==\n'
        git -C "$ROOT_DIR" ls-files \
            'app/**' \
            'bootstrap/**' \
            'config/**' \
            'database/**' \
            'docs/**' \
            'public/robots.txt' \
            'public/*.xml' \
            'resources/**' \
            'routes/**' \
            'scripts/**' \
            'tests/**' \
            'README.md' \
            'composer.json' \
            'package.json' \
            'phpunit.xml' \
            'vite.config.js' \
            | sort \
            || true
    else
        printf 'Not a Git work tree.\n'
    fi
} > "${OUTPUT_DIR}/08-git-state.txt"

printf 'Created SEO/business-site project dumps in: %s\n' "$OUTPUT_DIR"
printf '\nFiles:\n'
find "$OUTPUT_DIR" -maxdepth 1 -type f -printf '  %f\n' | sort
