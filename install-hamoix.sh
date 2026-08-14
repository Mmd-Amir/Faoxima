#!/usr/bin/env bash
# Hamoix web panel installer for Ubuntu/Debian.
# Installs the PHP runtime, downloads Hamoix, configures Nginx and cron,
# then leaves database setup to the web Wizard at /installer/.

set -Eeuo pipefail

readonly HAMOIX_REPO="hamed9898/Hamoix"
readonly HAMOIX_GITHUB="https://github.com/${HAMOIX_REPO}"
readonly HAMOIX_ARCHIVE="${HAMOIX_GITHUB}/archive/refs/heads/main.tar.gz"
readonly APP_DIR="${HAMOIX_APP_DIR:-/var/www/html/hamoix}"
readonly PHP_VERSION="${HAMOIX_PHP_VERSION:-8.2}"

log() { printf '[Hamoix] %s\n' "$*"; }
fail() { printf '[Hamoix][ERROR] %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || fail "این اسکریپت باید با دسترسی root اجرا شود."
[[ -f /etc/os-release ]] || fail "سیستم‌عامل شناسایی نشد."
source /etc/os-release
case "${ID:-}" in ubuntu|debian) ;; *) fail "برای Ubuntu/Debian آماده شده است." ;; esac

DOMAIN="${1:-}"
if [[ -z "${DOMAIN}" ]]; then read -r -p "دامنه‌ی Hamoix: " DOMAIN; fi
DOMAIN="${DOMAIN#https://}"; DOMAIN="${DOMAIN#http://}"; DOMAIN="${DOMAIN%%/*}"
[[ "${DOMAIN}" =~ ^[A-Za-z0-9.-]+$ ]] || fail "دامنه نامعتبر است."

log "نصب پیش‌نیازهای PHP ${PHP_VERSION}، Nginx و Composer..."
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y nginx mariadb-client curl ca-certificates tar unzip composer \
  "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
  "php${PHP_VERSION}-curl" "php${PHP_VERSION}-gd" "php${PHP_VERSION}-zip" \
  "php${PHP_VERSION}-intl" "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" \
  "php${PHP_VERSION}-bcmath"

tmp_dir="$(mktemp -d)"; trap 'rm -rf "${tmp_dir}"' EXIT
log "دریافت سورس Hamoix..."
curl -fL --retry 3 "${HAMOIX_ARCHIVE}" -o "${tmp_dir}/hamoix.tar.gz"
mkdir -p "${APP_DIR}"
tar -xzf "${tmp_dir}/hamoix.tar.gz" -C "${tmp_dir}"
source_dir="$(find "${tmp_dir}" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
[[ -n "${source_dir}" && -f "${source_dir}/index.php" ]] || fail "آرشیو Hamoix معتبر نیست."
cp -a "${source_dir}/." "${APP_DIR}/"

cd "${APP_DIR}"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
mkdir -p logs
chown -R www-data:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 755 {} +
find "${APP_DIR}" -type f -exec chmod 644 {} +

cat > "/etc/nginx/sites-available/hamoix-${DOMAIN}.conf" <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR};
    index index.php;
    client_max_body_size 32M;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock; }
    location ~* ^/(vendor|logs|storage)/ { deny all; return 404; }
}
NGINX
ln -sfn "/etc/nginx/sites-available/hamoix-${DOMAIN}.conf" "/etc/nginx/sites-enabled/hamoix-${DOMAIN}.conf"
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" nginx
systemctl reload nginx
( crontab -u www-data -l 2>/dev/null | grep -vF "${APP_DIR}/cron/cron.php" || true; echo "* * * * * php ${APP_DIR}/cron/cron.php >/dev/null 2>&1" ) | crontab -u www-data -

log "نصب Hamoix تکمیل شد؛ Wizard: http://${DOMAIN}/installer/"
log "پس از فعال‌سازی HTTPS نصب را ادامه دهید و بعد installer را حذف/مسدود کنید."
