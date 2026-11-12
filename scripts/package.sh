#!/usr/bin/env bash
# Operator entrypoint for audited Engage SEO selected-client package workflows.
# This wrapper never installs a package or changes feature activation itself.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/package.sh audit CLIENT_KEY [--source PATH] [--json]
  ./scripts/package.sh link CLIENT_KEY /absolute/path/to/local/package
  ./scripts/package.sh unlink CLIENT_KEY vendor/package

'audit' is read-only and client-isolated. 'link' and 'unlink' invoke the
existing SEO local-only development workflows; they require the selected
client and a local APP_ENV. Do not use local links in deployable releases.
Composer installation, package revisions, provider registration and feature
activation are separate operations; this command does not perform them.
USAGE
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 2
}

[[ -f "$ROOT_DIR/artisan" ]] || fail 'Package workflow requires an Engage SEO checkout.'

ACTION="${1:-}"
case "$ACTION" in
    -h|--help|help)
        usage
        exit 0
        ;;
    audit)
        shift
        exec python3 "$ROOT_DIR/scripts/operations/lib/package_audit_workflow.py" "$@"
        ;;
    link)
        [[ $# -eq 3 ]] || { usage >&2; exit 2; }
        CLIENT_KEY="$2"
        PACKAGE_DIR="$3"
        [[ "$CLIENT_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || fail 'Invalid client key.'
        [[ "$PACKAGE_DIR" = /* && -f "$PACKAGE_DIR/composer.json" ]] \
            || fail 'Link requires an absolute package checkout containing composer.json.'
        # Only the Composer package name is read. The existing SEO link helper
        # remains responsible for environment, selected-client, lock and link
        # safety validation. No PHP source or environment is executed here.
        PACKAGE_NAME="$(python3 - "$PACKAGE_DIR/composer.json" <<'PY'
import json
import re
import sys
from pathlib import Path

try:
    path = Path(sys.argv[1])
    if path.is_symlink() or path.stat().st_size > 2_000_000:
        raise ValueError("Unsafe package composer.json")
    document = json.loads(path.read_text(encoding="utf-8"))
    name = document.get("name") if isinstance(document, dict) else None
    if not isinstance(name, str) or re.fullmatch(r"[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*", name) is None:
        raise ValueError("Package composer.json has no valid vendor/package name")
except (OSError, UnicodeError, ValueError) as exc:
    print(f"Invalid package manifest: {exc}", file=sys.stderr)
    sys.exit(2)
print(name)
PY
)" || exit 2
        exec "$ROOT_DIR/scripts/dev-link-package.sh" "$PACKAGE_NAME" "$PACKAGE_DIR" "$CLIENT_KEY"
        ;;
    unlink)
        [[ $# -eq 3 ]] || { usage >&2; exit 2; }
        CLIENT_KEY="$2"
        PACKAGE_NAME="$3"
        [[ "$CLIENT_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || fail 'Invalid client key.'
        [[ "$PACKAGE_NAME" =~ ^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$ ]] || fail 'Invalid Composer package name.'
        exec "$ROOT_DIR/scripts/dev-unlink-package.sh" "$PACKAGE_NAME" "$CLIENT_KEY"
        ;;
    *)
        usage >&2
        exit 2
        ;;
esac