#!/usr/bin/env bash

set -Eeuo pipefail

usage() {
    cat <<'USAGE'
Usage:
  ./scripts/bootstrap-client-local.sh CLIENT_KEY [--local-url URL] [--no-select]

Sets up a new selected-client local MySQL database, protected client .env,
Laravel migrations, source-controlled bootstrap, and runtime validation.
Only APP_ENV=local and DB_CONNECTION=mysql with a loopback DB_HOST are supported.
Existing client databases are never dropped or wiped. Re-runs use the client
.env created by this script and reconcile only that client's database/user.

Options:
  --local-url URL  Local public URL (default: http://CLIENT_KEY.test)
  --no-select      Do not change the root .env CLIENT_KEY after validation
USAGE
}

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CLIENT_KEY="${1:-}"
LOCAL_URL=""
SELECT_CLIENT=true
WEB_GROUP="${ENGAGE_SEO_WEB_GROUP:-www-data}"
WEB_USER="${ENGAGE_SEO_WEB_USER:-www-data}"

if [[ "$CLIENT_KEY" == --help || "$CLIENT_KEY" == -h ]]; then
    usage
    exit 0
fi

[[ "$CLIENT_KEY" =~ ^[a-z0-9][a-z0-9_-]*$ ]] || { usage >&2; fail 'Invalid client key.'; }
shift

while (($#)); do
    case "$1" in
        --local-url)
            [[ $# -ge 2 ]] || fail '--local-url requires an argument.'
            LOCAL_URL="$2"
            shift 2
            ;;
        --no-select)
            SELECT_CLIENT=false
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *) fail "Unknown option: $1" ;;
    esac
done

[[ -n "$LOCAL_URL" ]] || LOCAL_URL="http://${CLIENT_KEY}.test"
[[ "$LOCAL_URL" =~ ^https?://[a-zA-Z0-9][a-zA-Z0-9.-]*(:[0-9]{1,5})?/?$ ]] \
    || fail 'Local URL must be an absolute http(s) origin, without a path, query, or fragment.'

for executable in php python3 sudo mysql; do
    command -v "$executable" >/dev/null 2>&1 || fail "Missing required command: $executable"
done

getent group "$WEB_GROUP" >/dev/null || fail "Missing PHP-FPM group: $WEB_GROUP"
getent passwd "$WEB_USER" >/dev/null || fail "Missing PHP-FPM user: $WEB_USER"

ROOT_ENV="$ROOT_DIR/.env"
CLIENT_DIR="$ROOT_DIR/client/$CLIENT_KEY"
CLIENT_ENV="$CLIENT_DIR/.env"
CLIENT_ENV_EXAMPLE="$CLIENT_DIR/.env.example"

[[ -f "$ROOT_DIR/artisan" && -f "$ROOT_DIR/vendor/autoload.php" ]] \
    || fail 'Engage SEO must have installed PHP dependencies.'
[[ -f "$ROOT_ENV" && ! -L "$ROOT_ENV" ]] || fail 'Expected a regular root .env.'
[[ -f "$CLIENT_DIR/config/client.php" && -f "$CLIENT_ENV_EXAMPLE" ]] \
    || fail 'The client scaffold is missing.'
[[ ! -L "$CLIENT_DIR" && ! -L "$CLIENT_ENV" ]] || fail 'Refusing symlinked client paths.'

# Read only non-secret platform values. Do not source .env into a shell.
mapfile -t PLATFORM < <(php -r '
require $argv[1]."/vendor/autoload.php";
$v = Dotenv\Dotenv::createArrayBacked($argv[1], ".env")->safeLoad();
foreach (["APP_ENV", "DB_CONNECTION", "DB_HOST"] as $key) {
    echo trim((string) ($v[$key] ?? "")), PHP_EOL;
}
' "$ROOT_DIR")
[[ "${PLATFORM[0]:-}" == local ]] || fail 'Refusing: root APP_ENV must be local.'
[[ "${PLATFORM[1]:-mysql}" == mysql ]] || fail 'Refusing: root DB_CONNECTION must be mysql.'
DB_HOST="${PLATFORM[2]:-127.0.0.1}"
[[ "$DB_HOST" == localhost || "$DB_HOST" == 127.0.0.1 ]] \
    || fail 'Refusing: local bootstrap requires a loopback MySQL host.'

# Derive stable MySQL identifiers within the MySQL identifier and username limits.
mapfile -t MYSQL_IDENTITIES < <(python3 - "$CLIENT_KEY" <<'PY'
import hashlib
import sys
key = sys.argv[1].replace('-', '_')
digest = hashlib.sha256(key.encode()).hexdigest()[:10]
print(f"engage_seo_{key[:38]}_{digest}"[:64])
print(f"seo_{key[:14]}_{digest}"[:32])
PY
)
DATABASE="${MYSQL_IDENTITIES[0]}"
DATABASE_USER="${MYSQL_IDENTITIES[1]}"
DATABASE_HOST="$DB_HOST"

# Prefer the Ubuntu root socket account, falling back to interactive root MySQL
# authentication. The password, if required, is entered through MySQL's TTY.
MYSQL_ADMIN=(sudo mysql --protocol=socket)
if ! "${MYSQL_ADMIN[@]}" --batch --skip-column-names -e 'SELECT 1' >/dev/null 2>&1; then
    MYSQL_ADMIN=(sudo mysql --protocol=socket -p)
    "${MYSQL_ADMIN[@]}" --batch --skip-column-names -e 'SELECT 1' >/dev/null \
        || fail 'Unable to authenticate to local MySQL as an administrator.'
fi

CREATED_ENV=false
if [[ -e "$CLIENT_ENV" ]]; then
    [[ -f "$CLIENT_ENV" && ! -L "$CLIENT_ENV" ]] || fail 'Refusing non-regular client .env.'
    # Existing .env may be reused only if it contains the exact generated
    # database identity. No existing client credentials are overwritten.
    mapfile -t EXISTING < <(php -r '
require $argv[1]."/vendor/autoload.php";
$v = Dotenv\Dotenv::createArrayBacked($argv[2], ".env")->safeLoad();
foreach (["DB_DATABASE", "DB_USERNAME", "APP_URL"] as $key) {
    echo trim((string) ($v[$key] ?? "")), PHP_EOL;
}
' "$ROOT_DIR" "$CLIENT_DIR")
    [[ "${EXISTING[0]:-}" == "$DATABASE" && "${EXISTING[1]:-}" == "$DATABASE_USER" ]] \
        || fail 'Client .env has a different database identity. Refusing to change it.'
    [[ "${EXISTING[2]:-}" == "$LOCAL_URL" ]] \
        || fail 'Existing client APP_URL differs; pass its original --local-url.'
else
    # Preflight existing database/user before writing any credentials.
    DB_PRESENT="$("${MYSQL_ADMIN[@]}" --batch --skip-column-names \
        -e "SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='${DATABASE}'")"
    USER_PRESENT="$("${MYSQL_ADMIN[@]}" --batch --skip-column-names \
        -e "SELECT COUNT(*) FROM mysql.user WHERE user='${DATABASE_USER}'")"
    [[ "$DB_PRESENT" == 0 && "$USER_PRESENT" == 0 ]] \
        || fail 'Generated database/user already exists without a matching client .env; refusing takeover.'

    SECRET="$(python3 - <<'PY'
import secrets
print(secrets.token_hex(24))
PY
)"
    TEMP_ENV="$(mktemp)"
    trap 'rm -f -- "${TEMP_ENV:-}"' EXIT
    {
        printf '# Created by scripts/bootstrap-client-local.sh. Never commit.\n'
        printf 'APP_URL=%s\n' "$LOCAL_URL"
        printf 'DB_DATABASE=%s\n' "$DATABASE"
        printf 'DB_USERNAME=%s\n' "$DATABASE_USER"
        printf 'DB_PASSWORD=%s\n' "$SECRET"
        printf 'CACHE_PREFIX=seo-%s-local-cache-\n' "$CLIENT_KEY"
        printf 'REDIS_PREFIX=seo-%s-local-\n' "$CLIENT_KEY"
    } > "$TEMP_ENV"
    chmod 600 "$TEMP_ENV"
    sudo install -o "$(id -un)" -g "$WEB_GROUP" -m 640 "$TEMP_ENV" "$CLIENT_ENV"
    rm -f "$TEMP_ENV"
    trap - EXIT
    CREATED_ENV=true
fi

# Get the credential only in-process, never on stdout. No SQL or password
# values are passed in command-line arguments.
DB_PASSWORD_VALUE="$(php -r '
require $argv[1]."/vendor/autoload.php";
$v = Dotenv\Dotenv::createArrayBacked($argv[2], ".env")->safeLoad();
echo $v["DB_PASSWORD"] ?? "";
' "$ROOT_DIR" "$CLIENT_DIR")"
[[ "$DB_PASSWORD_VALUE" =~ ^[a-f0-9]{48}$ ]] \
    || fail 'This bootstrap only resumes its own generated 48-character hex password.'

# Escape identifiers via a strict generated identifier alphabet, and password
# via a fixed hex alphabet. This only creates/grants this selected client's user.
[[ "$DATABASE" =~ ^[a-z0-9_]+$ && "$DATABASE_USER" =~ ^[a-z0-9_]+$ ]] \
    || fail 'Invalid derived MySQL identifiers.'
MYSQL_GRANT_HOST="$DATABASE_HOST"

SQL="$(cat <<SQL
CREATE DATABASE IF NOT EXISTS \`$DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DATABASE_USER'@'$MYSQL_GRANT_HOST' IDENTIFIED BY '$DB_PASSWORD_VALUE';
ALTER USER '$DATABASE_USER'@'$MYSQL_GRANT_HOST' IDENTIFIED BY '$DB_PASSWORD_VALUE';
GRANT ALL PRIVILEGES ON \`$DATABASE\`.* TO '$DATABASE_USER'@'$MYSQL_GRANT_HOST';
SQL
)"

if ! printf '%s\n' "$SQL" | "${MYSQL_ADMIN[@]}" --batch >/dev/null; then
    unset SQL DB_PASSWORD_VALUE SECRET
    fail 'MySQL setup failed. Client .env was retained for a safe rerun.'
fi
unset SQL DB_PASSWORD_VALUE SECRET

cd "$ROOT_DIR"
# The explicit process CLIENT_KEY overrides the root selection until final
# success. Never migrate or bootstrap the previously selected client.
# Clear only compiled configuration before migrations. A brand-new client
# database does not yet have a database-backed cache table, so running
# optimize:clear (which invokes cache:clear) here would fail prematurely.
CLIENT_KEY="$CLIENT_KEY" php artisan config:clear --no-ansi
CLIENT_KEY="$CLIENT_KEY" php artisan migrate --force --no-ansi
CLIENT_KEY="$CLIENT_KEY" php artisan optimize:clear --no-ansi
CLIENT_KEY="$CLIENT_KEY" php artisan client:bootstrap --no-ansi
CLIENT_KEY="$CLIENT_KEY" php artisan setup:validate --no-ansi

# Check the real web identity rather than trusting a successful developer CLI.
sudo -u "$WEB_USER" test -r "$CLIENT_ENV" \
    || fail 'The PHP-FPM identity cannot read the client .env.'
sudo -u "$WEB_USER" test -r "$CLIENT_DIR/config/client.php" \
    || fail 'The PHP-FPM identity cannot read client configuration.'
sudo -u "$WEB_USER" env CLIENT_KEY="$CLIENT_KEY" php artisan about --only=environment --no-ansi >/dev/null \
    || fail 'Laravel cannot boot as the PHP-FPM identity.'

if [[ "$SELECT_CLIENT" == true ]]; then
    # Update CLIENT_KEY last, atomically, preserving owner/group/mode and every
    # unrelated root setting. Refuse ambiguous duplicate selection lines.
    python3 - "$ROOT_ENV" "$CLIENT_KEY" <<'PY'
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

path = Path(sys.argv[1])
key = sys.argv[2]
if not path.is_file() or path.is_symlink():
    raise SystemExit('Refusing unsafe root .env.')
current = path.read_text()
pattern = re.compile(r'(?m)^([ \t]*(?:export[ \t]+)?CLIENT_KEY[ \t]*=).*$')
count = len(pattern.findall(current))
if count > 1:
    raise SystemExit('Root .env has multiple CLIENT_KEY assignments; refusing modification.')
if count:
    updated = pattern.sub(lambda match: match.group(1) + key, current)
else:
    updated = current.rstrip('\n') + '\nCLIENT_KEY=' + key + '\n'
if updated != current:
    st = path.stat()
    if st.st_uid != os.getuid():
        raise SystemExit('Root .env is not owned by the current deploy user.')
    fd, temp = tempfile.mkstemp(prefix='.env.client-', dir=path.parent)
    try:
        with os.fdopen(fd, 'w') as stream:
            stream.write(updated)
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(temp, st.st_mode & 0o777)
        try:
            os.chown(temp, st.st_uid, st.st_gid)
        except PermissionError:
            subprocess.run(['sudo', 'chown', '--reference=' + str(path), temp], check=True)
        os.replace(temp, path)
    finally:
        Path(temp).unlink(missing_ok=True)
PY
fi

printf '\nEngage SEO local client ready\n'
printf '  Client:   %s\n' "$CLIENT_KEY"
printf '  URL:      %s\n' "$LOCAL_URL"
printf '  Database: %s\n' "$DATABASE"
printf '  Selected: %s\n' "$SELECT_CLIENT"