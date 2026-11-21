#!/bin/sh
set -e

APP_DIR="/var/www/faoxima"
cd "$APP_DIR"

export FAOXIMA_DOCKER_ENV=1

PHP_RUNTIME_USER=$(grep -hE '^[[:space:]]*user[[:space:]]*=' /usr/local/etc/php-fpm.d/*.conf | tail -n1 | sed -E 's/^[^=]*=[[:space:]]*//; s/[[:space:]]*(;.*)?$//')
PHP_RUNTIME_GROUP=$(grep -hE '^[[:space:]]*group[[:space:]]*=' /usr/local/etc/php-fpm.d/*.conf | tail -n1 | sed -E 's/^[^=]*=[[:space:]]*//; s/[[:space:]]*(;.*)?$//')
PHP_RUNTIME_USER=${PHP_RUNTIME_USER:-www-data}
PHP_RUNTIME_GROUP=${PHP_RUNTIME_GROUP:-$PHP_RUNTIME_USER}
id -u "$PHP_RUNTIME_USER" >/dev/null
getent group "$PHP_RUNTIME_GROUP" >/dev/null

mkdir -p "$APP_DIR/cron" "$APP_DIR/cronbot/.runtime" "$APP_DIR/logs"

CURRENT_DBNAME=$(grep '^\$dbname' config.php | awk -F"'" '{print $2}')
if [ -z "$CURRENT_DBNAME" ]; then
    echo "[entrypoint] config.php has no database configured — templating from environment..."
    php "$APP_DIR/docker/php-entrypoint-configure.php"
fi

chown -R "$PHP_RUNTIME_USER:$PHP_RUNTIME_GROUP" "$APP_DIR"
find "$APP_DIR" -path "$APP_DIR/installer" -prune -o -type d -exec chmod 775 {} +
find "$APP_DIR" -path "$APP_DIR/installer" -prune -o -type f -exec chmod 664 {} +
if [ -d "$APP_DIR/installer" ]; then
    chmod 555 "$APP_DIR/installer"
    find "$APP_DIR/installer" -type f -exec chmod 444 {} +
fi
if [ -f "$APP_DIR/config.php" ]; then
    chmod 600 "$APP_DIR/config.php"
fi
if [ -f "$APP_DIR/.env" ]; then
    chmod 600 "$APP_DIR/.env"
fi

if [ -d "$APP_DIR/storage/private" ]; then
    chmod 700 "$APP_DIR/storage/private"
    find "$APP_DIR/storage/private" -type f -exec chmod 600 {} +
fi

su -s /bin/sh -g "$PHP_RUNTIME_GROUP" "$PHP_RUNTIME_USER" -c '
    cd /var/www/faoxima || exit 1
    failed=0
    check() {
        f=""
        if f=$(mktemp "$1/.permission-test.XXXXXX") && printf "%s" permission-ok > "$f" && [ "$(cat "$f")" = permission-ok ] && rm -f "$f" && [ ! -e "$f" ]; then
            printf "[entrypoint] Runtime access verified: %s\n" "$1"
        else
            [ -z "$f" ] || rm -f "$f"
            printf "[entrypoint] Runtime access failed: %s\n" "$1" >&2
            failed=1
        fi
    }
    for d in . cron cronbot cronbot/.runtime logs; do check "$d"; done
    [ ! -d storage/private ] || check storage/private
    for f in config.php function.php cron/cron.php; do
        if [ ! -r "$f" ]; then
            printf "[entrypoint] Required file is not readable: %s\n" "$f" >&2
            failed=1
        fi
    done
    exit "$failed"
'

if command -v cron >/dev/null 2>&1; then
    mkdir -p /var/spool/cron/crontabs 2>/dev/null || true
    chown www-data:crontab /var/spool/cron/crontabs 2>/dev/null || true
    chmod 1730 /var/spool/cron/crontabs 2>/dev/null || true
    cron
fi

exec "$@"
