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
  ./scripts/package.sh update CLIENT_KEY vendor/package --plan [--json]
  ./scripts/package.sh update CLIENT_KEY vendor/package --apply --confirm-plan FINGERPRINT [--json]
  ./scripts/package.sh repair CLIENT_KEY vendor/package --plan [--json]
  ./scripts/package.sh repair CLIENT_KEY vendor/package --apply --confirm-plan FINGERPRINT [--json]

'audit' is read-only and client-isolated. 'link' and 'unlink' invoke the
existing SEO local-only development workflows; they require the selected
client and a local APP_ENV. Do not use local links in deployable releases.
Update/repair delegate to the shared Infrastructure local Composer engine.
Registration, feature activation and migrations remain SEO-owned operations.
USAGE
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 2
}

[[ -f "$ROOT_DIR/artisan" ]] || fail 'Package workflow requires an Engage SEO checkout.'

revision_operation() {
    local action="$1"
    shift
    if [[ $# -lt 3 ]]; then
        printf 'Usage: ./scripts/package.sh %s CLIENT_KEY vendor/package (--plan | --apply --confirm-plan FINGERPRINT) [--json]\n' "$action" >&2
        return 2
    fi
    local client_key="$1" package_name="$2"
    shift 2
    [[ "$client_key" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || { printf 'ERROR: Invalid client key.\n' >&2; return 2; }
    [[ "$package_name" =~ ^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$ ]] || { printf 'ERROR: Invalid Composer package name.\n' >&2; return 2; }

    # Only forward the shared contract's public mode/output options. Do not
    # allow callers to override --platform, --root, --client, --package, or --env.
    local mode='' seen_json=0 seen_confirm=0
    local -a options=()
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --plan|--apply)
                [[ -z "$mode" ]] || { printf 'ERROR: Choose exactly one --plan or --apply.\n' >&2; return 2; }
                mode="$1"
                options+=("$1")
                ;;
            --json)
                [[ "$seen_json" -eq 0 ]] || { printf 'ERROR: Duplicate --json.\n' >&2; return 2; }
                seen_json=1
                options+=("$1")
                ;;
            --confirm-plan)
                [[ "$seen_confirm" -eq 0 && $# -ge 2 && "$2" =~ ^[a-f0-9]{64}$ ]] || {
                    printf 'ERROR: --confirm-plan requires one 64-character lowercase hexadecimal fingerprint.\n' >&2; return 2;
                }
                seen_confirm=1
                options+=("$1" "$2")
                shift
                ;;
            --confirm-plan=*)
                local fingerprint="${1#--confirm-plan=}"
                [[ "$seen_confirm" -eq 0 && "$fingerprint" =~ ^[a-f0-9]{64}$ ]] || {
                    printf 'ERROR: Invalid or duplicate --confirm-plan fingerprint.\n' >&2; return 2;
                }
                seen_confirm=1
                options+=("$1")
                ;;
            *)
                printf 'ERROR: Unknown package revision option.\n' >&2
                return 2
                ;;
        esac
        shift
    done
    [[ -n "$mode" ]] || { printf 'ERROR: --plan or --apply is required.\n' >&2; return 2; }
    if [[ "$mode" == --plan && "$seen_confirm" -eq 1 ]]; then
        printf 'ERROR: --confirm-plan is valid only with --apply.\n' >&2
        return 2
    fi

    local infrastructure_root="${ENGAGE_INFRASTRUCTURE_ROOT:-$HOME/Projects/engage-infrastructure}"
    local engage_cli="$infrastructure_root/scripts/engage"
    local shared_engine="$infrastructure_root/scripts/lib/package_revision.py"
    [[ "$infrastructure_root" == /* ]] || { printf 'ERROR: Infrastructure root must be an absolute path.\n' >&2; return 2; }
    if [[ -e "$infrastructure_root" || -L "$infrastructure_root" ]]; then
        if [[ -L "$infrastructure_root" || ! -d "$infrastructure_root" ||
              -L "$infrastructure_root/scripts" || -L "$infrastructure_root/scripts/lib" ||
              -L "$engage_cli" || ! -f "$engage_cli" ||
              -L "$shared_engine" || ! -f "$shared_engine" ]]; then
            printf 'ERROR: Infrastructure checkout does not contain the verified B51 package revision command.\n' >&2
            return 1
        fi
        exec python3 "$engage_cli" package "$action" \
            --env local --platform seo --root "$ROOT_DIR" \
            --client "$client_key" --package "$package_name" "${options[@]}"
    fi

    # SEO had no independent update/repair command before this adapter.
    # Its existing audit and development link operations remain standalone.
    printf 'ERROR: Reviewed package update/repair requires Engage Infrastructure B51. Set ENGAGE_INFRASTRUCTURE_ROOT to its checkout.\n' >&2
    return 1
}

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
    update|repair)
        shift
        revision_operation "$ACTION" "$@"
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