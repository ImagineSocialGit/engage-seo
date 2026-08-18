#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ "${1:-}" == "--force" ]]; then
    "$ROOT_DIR/scripts/dev-reset-client-database.sh" --force
elif [[ $# -eq 0 ]]; then
    "$ROOT_DIR/scripts/dev-reset-client-database.sh"
else
    echo "Usage: ./scripts/dev-refresh-client.sh [--force]"
    exit 1
fi

php artisan migrate --force

echo
echo "Selected Engage SEO client database refreshed."