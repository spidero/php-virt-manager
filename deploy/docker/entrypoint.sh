#!/bin/sh
# Prepares config.php, TLS certificate, nginx and php-fpm configuration
# from environment variables, then runs the given command (supervisord).
set -eu

APP_DIR=/app
FPM_SOCK=/run/php/php-virt-manager.sock

# start banner: version (from the image build), author and where to report problems
read -r PVM_VERSION PVM_REVISION < "$APP_DIR/VERSION" 2>/dev/null || { PVM_VERSION=dev; PVM_REVISION=unknown; }
# in a plain ASCII frame sized to the longest line
awk '{ line[NR] = $0; if (length($0) > w) w = length($0) }
     END {
         border = "+"; for (i = 0; i < w + 2; i++) border = border "-"; border = border "+"
         print border
         for (i = 1; i <= NR; i++) printf "| %-" w "s |\n", line[i]
         print border
     }' <<EOF
php-virt-manager $PVM_VERSION
revision $(printf '%.7s' "$PVM_REVISION"), $(php -r 'echo "PHP ", PHP_VERSION;')

Author:  Piotr Skwarna (spidero) - https://github.com/spidero
Project: https://github.com/spidero/php-virt-manager
Ideas and bug reports: https://github.com/spidero/php-virt-manager/issues
EOF
SSL_DIR=/etc/nginx/ssl
SOCK=/var/run/libvirt/libvirt-sock

# system time zone from $TZ: php-fpm clears the environment (clear_env), so the
# panel pages only see the zone through /etc/localtime and /etc/timezone
if [ -n "${TZ:-}" ]; then
    if [ -f "/usr/share/zoneinfo/$TZ" ]; then
        ln -sf "/usr/share/zoneinfo/$TZ" /etc/localtime
        echo "$TZ" > /etc/timezone
    else
        echo "WARNING: unknown time zone TZ=$TZ, using UTC" >&2
    fi
fi

# access to the host libvirt socket: join a group with the socket's gid
if [ -S "$SOCK" ]; then
    GID=$(stat -c %g "$SOCK")
    # gid 0: a root-owned socket (usually mode 666, access decided by polkit) -
    # never put www-data into the root group
    if [ "$GID" -ne 0 ]; then
        GROUP=$(getent group "$GID" | cut -d: -f1 || true)
        if [ -z "$GROUP" ]; then
            GROUP=libvirt-host
            groupadd -g "$GID" "$GROUP"
        fi
        usermod -aG "$GROUP" www-data
    fi
    # polkit on the host checks the host's groups of uid 33, not the container's
    if [ "$(stat -c %a "$SOCK")" = 666 ]; then
        echo "NOTE: libvirt access is checked by polkit on the host; if the panel reports" \
             "'no polkit agent available', run on the host: usermod -aG libvirt \$(id -nu 33)" >&2
    fi
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
            "connections"        => ["local" => ["uri" => getenv("LIBVIRT_URI"), "label" => getenv("LIBVIRT_URI"), "readonly" => (int)getenv("PVM_READONLY")]],
            "auth_user"          => getenv("PVM_USER"),
            "auth_password_hash" => getenv("PVM_HASH"),
            "console_enabled"    => true,
        ];
        // optional: own catalog list, automatic synchronization on/off
        if (getenv("PVM_CLOUD_CATALOG_URL")) {
            $set["cloud_catalog_url"] = getenv("PVM_CLOUD_CATALOG_URL");
        }
        if (getenv("PVM_CLOUD_CATALOG_SYNC") !== false) {
            $set["cloud_catalog_auto_sync"] = getenv("PVM_CLOUD_CATALOG_SYNC") !== "0";
        }
        foreach ($set as $k => $v) {
            // callback: a bcrypt hash contains "$2y$..." which would be read as backreferences;
            // /s: the $connections array spans several lines
            $c = preg_replace_callback("/^\\\$$k = .*?;$/ms", fn() => "\$$k = ".var_export($v, true).";", $c);
        }
        file_put_contents("/app/config.php", $c);'
    chown root:www-data "$APP_DIR/config.php"
    chmod 640 "$APP_DIR/config.php"
fi

# an explicitly set password always wins over the one stored in the user
# database (config.php alone only seeds the very first user)
if [ -n "${PVM_PASSWORD:-}${PVM_PASSWORD_HASH:-}" ]; then
    if [ -n "${PVM_PASSWORD:-}" ] && [ "${#PVM_PASSWORD}" -lt 8 ]; then
        echo "WARNING: PVM_PASSWORD is shorter than 8 characters" >&2
    fi
    mkdir -p "$APP_DIR/data"
    chown www-data:www-data "$APP_DIR/data"
    su -s /bin/sh www-data -c "php $APP_DIR/bin/set-password.php"
    echo "NOTE: PVM_PASSWORD/PVM_PASSWORD_HASH is applied on every start - a password of" \
         "'$PVM_USER' changed in the panel is overwritten by the next restart." \
         "Remove the variable to manage the password in the panel." >&2
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
