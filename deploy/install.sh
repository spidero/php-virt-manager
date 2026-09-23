#!/bin/sh
# Installs php-virt-manager on Debian/Ubuntu with nginx + php-fpm + websockify.
# Run as root from the repository directory:  sudo deploy/install.sh
# Settings (environment): APP_DIR, HTTP_PORT, HTTPS_PORT, WS_PORT, PHP_VERSION
set -eu

SRC_DIR=$(cd "$(dirname "$0")/.." && pwd)
APP_DIR=${APP_DIR:-/var/www/php-virt-manager}
HTTP_PORT=${HTTP_PORT:-8090}
HTTPS_PORT=${HTTPS_PORT:-8443}
WS_PORT=${WS_PORT:-6080}
APP_USER=www-data
APP_GROUP=libvirt
SSL_DIR=/etc/ssl/php-virt-manager

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)." >&2; exit 1; }

echo "== packages"
apt-get update -q
apt-get install -y -q nginx php-fpm php-cli php-xml php-mbstring php-libvirt-php composer novnc websockify rsync openssl
PHP_VERSION=${PHP_VERSION:-$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')}
FPM_SOCK=/run/php/php-virt-manager.sock

echo "== libvirt extension"
# Ubuntu package ships libvirt-php.so without an ini file
if [ ! -f "/etc/php/$PHP_VERSION/mods-available/libvirt.ini" ]; then
    echo "extension=libvirt-php.so" > "/etc/php/$PHP_VERSION/mods-available/libvirt.ini"
fi
phpenmod -v "$PHP_VERSION" libvirt

echo "== application in $APP_DIR"
mkdir -p "$APP_DIR"
rsync -a --delete \
    --exclude .git --exclude .github --exclude vendor --exclude templates_c --exclude cache \
    --exclude configs --exclude data --exclude config.php --exclude '*.md' --exclude docs \
    --include README.md "$SRC_DIR/" "$APP_DIR/"
(cd "$APP_DIR" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress -q)
mkdir -p "$APP_DIR/templates_c" "$APP_DIR/cache" "$APP_DIR/configs" "$APP_DIR/data/tokens"
chown -R root:root "$APP_DIR"
chown -R "$APP_USER:$APP_GROUP" "$APP_DIR/templates_c" "$APP_DIR/cache" "$APP_DIR/configs" "$APP_DIR/data"
chmod 770 "$APP_DIR/templates_c" "$APP_DIR/cache" "$APP_DIR/configs" "$APP_DIR/data" "$APP_DIR/data/tokens"

if [ ! -f "$APP_DIR/config.php" ]; then
    PASSWORD=$(openssl rand -base64 12)
    HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$PASSWORD")
    sed -e "s|^\$auth_password_hash = '';|\$auth_password_hash = '$HASH';|" \
        -e "s|^\$console_enabled = false;|\$console_enabled = true;|" \
        "$APP_DIR/config-default.php" > "$APP_DIR/config.php"
    chown "root:$APP_GROUP" "$APP_DIR/config.php"
    chmod 640 "$APP_DIR/config.php"
    echo "   login: admin / $PASSWORD  (change in $APP_DIR/config.php)"
fi

echo "== TLS certificate"
if [ ! -f "$SSL_DIR/cert.pem" ]; then
    mkdir -p "$SSL_DIR"
    openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=$(hostname -f)" \
        -keyout "$SSL_DIR/key.pem" -out "$SSL_DIR/cert.pem" 2>/dev/null
    chmod 600 "$SSL_DIR/key.pem"
    echo "   self-signed certificate created in $SSL_DIR (replace with a real one if needed)"
fi

fill() {
    sed -e "s|@ROOT@|$APP_DIR|g" -e "s|@FPM_SOCK@|$FPM_SOCK|g" -e "s|@NOVNC_DIR@|/usr/share/novnc|g" \
        -e "s|@CERT@|$SSL_DIR/cert.pem|g" -e "s|@KEY@|$SSL_DIR/key.pem|g" \
        -e "s|@HTTP_PORT@|$HTTP_PORT|g" -e "s|@HTTPS_PORT@|$HTTPS_PORT|g" -e "s|@WS_PORT@|$WS_PORT|g" \
        -e "s|@USER@|$APP_USER|g" -e "s|@GROUP@|$APP_GROUP|g" -e "s|@NGINX_USER@|www-data|g" "$1"
}

echo "== php-fpm pool"
fill "$SRC_DIR/deploy/php-fpm-pool.conf.in" > "/etc/php/$PHP_VERSION/fpm/pool.d/php-virt-manager.conf"
systemctl restart "php$PHP_VERSION-fpm"

echo "== websockify service"
fill "$SRC_DIR/deploy/websockify.service.in" > /etc/systemd/system/php-virt-manager-websockify.service
systemctl daemon-reload
systemctl enable --now php-virt-manager-websockify.service
systemctl restart php-virt-manager-websockify.service

echo "== nginx"
fill "$SRC_DIR/deploy/nginx.conf.in" > /etc/nginx/sites-available/php-virt-manager
ln -sf /etc/nginx/sites-available/php-virt-manager /etc/nginx/sites-enabled/php-virt-manager
nginx -t
systemctl enable --now nginx
systemctl reload nginx

echo "== done: https://$(hostname -f):$HTTPS_PORT/"
