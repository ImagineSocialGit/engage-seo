#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ ! -f "$ROOT_DIR/artisan" ]]; then
    printf 'ERROR: Package workflow requires an Engage SEO checkout.\n' >&2
    exit 1
fi

if [[ "${1:-}" != "audit" ]]; then
    printf 'Usage: ./scripts/package.sh audit CLIENT_KEY [--source PATH] [--json]\n' >&2
    printf 'Existing local package linking remains in scripts/dev-link-package.sh and scripts/dev-unlink-package.sh.\n' >&2
    exit 2
fi
shift
exec python3 "$ROOT_DIR/scripts/operations/lib/package_audit_workflow.py" "$@"