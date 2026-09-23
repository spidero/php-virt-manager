#!/bin/sh
# Prepares config.php, TLS certificate, nginx and php-fpm configuration
# from environment variables, then runs the given command (supervisord).
set -eu

APP_DIR=/app
FPM_SOCK=/run/php/php-virt-manager.sock
SSL_DIR=/etc/nginx/ssl
SOCK=/var/run/libvirt/libvirt-sock

# access to the host libvirt socket: join a group with the socket's gid
if [ -S "$SOCK" ]; then
    GID=$(stat -c %g "$SOCK")
    GROUP=$(getent group "$GID" | cut -d: -f1 || true)
    if [ -z "$GROUP" ]; then
        GROUP=libvirt-host
        groupadd -g "$GID" "$GROUP"
    fi
    usermod -aG "$GROUP" www-data
else
    echo "WARNING: $SOCK not found - mount /var/run/libvirt from the host" >&2
fi

# config.php from environment unless mounted
if [ ! -f "$APP_DIR/config.php" ] || [ -n "${PVM_PASSWORD:-}${PVM_PASSWORD_HASH:-}" ]; then
    HASH=${PVM_PASSWORD_HASH:-}
    if [ -z "$HASH" ]; then
        PASSWORD=${PVM_PASSWORD:-$(openssl rand -base64 12)}
        [ -n "${PVM_PASSWORD:-}" ] || echo "Generated login: $PVM_USER / $PASSWORD (set PVM_PASSWORD to choose one)"
        HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$PASSWORD")
    fi
    PVM_HASH="$HASH" php -r '
        $c = file_get_contents("/app/config-default.php");
        $set = [
            "connection"         => getenv("LIBVIRT_URI"),
            "readonly"           => (int)getenv("PVM_READONLY"),
            "auth_user"          => getenv("PVM_USER"),
            "auth_password_hash" => getenv("PVM_HASH"),
            "console_enabled"    => true,
        ];
        foreach ($set as $k => $v) {
            // callback: a bcrypt hash contains "$2y$..." which would be read as backreferences
            $c = preg_replace_callback("/^\\\$$k = .*;$/m", fn() => "\$$k = ".var_export($v, true).";", $c);
        }
        file_put_contents("/app/config.php", $c);'
    chown root:www-data "$APP_DIR/config.php"
    chmod 640 "$APP_DIR/config.php"
fi

# self-signed certificate unless one is mounted
if [ ! -f "$SSL_DIR/cert.pem" ]; then
    mkdir -p "$SSL_DIR"
    openssl req -x509 -newkey rsa:2048 -nodes -days 3650 -subj "/CN=php-virt-manager" \
        -keyout "$SSL_DIR/key.pem" -out "$SSL_DIR/cert.pem" 2>/dev/null
fi

fill() {
    sed -e "s|@ROOT@|$APP_DIR|g" -e "s|@FPM_SOCK@|$FPM_SOCK|g" -e "s|@NOVNC_DIR@|/usr/share/novnc|g" \
        -e "s|@CERT@|$SSL_DIR/cert.pem|g" -e "s|@KEY@|$SSL_DIR/key.pem|g" \
        -e "s|@HTTP_PORT@|$HTTP_PORT|g" -e "s|@HTTPS_PORT@|$HTTPS_PORT|g" -e "s|@WS_PORT@|$WS_PORT|g" \
        -e "s|@USER@|www-data|g" -e "s|@GROUP@|www-data|g" -e "s|@NGINX_USER@|www-data|g" "$1"
}
fill "$APP_DIR/deploy/nginx.conf.in" > /etc/nginx/sites-enabled/php-virt-manager
rm -f /etc/php/8.5/fpm/pool.d/www.conf
fill "$APP_DIR/deploy/php-fpm-pool.conf.in" > /etc/php/8.5/fpm/pool.d/php-virt-manager.conf
mkdir -p /run/php "$APP_DIR/data/tokens"
chown -R www-data:www-data "$APP_DIR/data"

exec "$@"
