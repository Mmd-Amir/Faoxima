#!/bin/sh
set -e

APP_DIR="/var/www/faoxima"
cd "$APP_DIR"

export FAOXIMA_DOCKER_ENV=1

CURRENT_DBNAME=$(grep '^\$dbname' config.php | awk -F"'" '{print $2}')
if [ -z "$CURRENT_DBNAME" ]; then
    echo "[entrypoint] config.php has no database configured — templating from environment..."
    php "$APP_DIR/docker/php-entrypoint-configure.php"
fi

chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true
find "$APP_DIR" -path "$APP_DIR/installer" -prune -o -type d -exec chmod 775 {} \; 2>/dev/null || true
find "$APP_DIR" -path "$APP_DIR/installer" -prune -o -type f -exec chmod 664 {} \; 2>/dev/null || true
if [ -d "$APP_DIR/installer" ]; then
    chmod 555 "$APP_DIR/installer" 2>/dev/null || true
    find "$APP_DIR/installer" -type f -exec chmod 444 {} \; 2>/dev/null || true
fi
if [ -f "$APP_DIR/config.php" ]; then
    chown www-data:www-data "$APP_DIR/config.php" 2>/dev/null || true
    chmod 600 "$APP_DIR/config.php" 2>/dev/null || true
fi
if [ -f "$APP_DIR/.env" ]; then
    chmod 600 "$APP_DIR/.env" 2>/dev/null || true
fi

if [ -d "$APP_DIR/storage/private" ]; then
    chmod 700 "$APP_DIR/storage/private"
    find "$APP_DIR/storage/private" -type f -exec chmod 600 {} \;
fi

if command -v cron >/dev/null 2>&1; then
    mkdir -p /var/spool/cron/crontabs 2>/dev/null || true
    chown www-data:crontab /var/spool/cron/crontabs 2>/dev/null || true
    chmod 1730 /var/spool/cron/crontabs 2>/dev/null || true
    cron
fi

exec "$@"
