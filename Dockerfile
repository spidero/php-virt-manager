# php-virt-manager: nginx + php-fpm + websockify (noVNC) in one image.
# Needs the host libvirt socket and host networking (VNC listens on host localhost),
# see docker-compose.yml.

# PHP dependencies and the Bootstrap CSS; composer itself stays out of the image.
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --ignore-platform-reqs \
    && rm -rf vendor/twbs

FROM ubuntu:26.04

ARG DEBIAN_FRONTEND=noninteractive
# novnc is unpacked without its dependencies: only its static files are served,
# the package would pull in nodejs (~150 MB).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx php8.5-fpm php8.5-cli php8.5-xml php8.5-mbstring php8.5-sqlite3 php8.5-curl php8.5-libvirt-php \
        websockify imagemagick supervisor openssl ca-certificates \
        xorriso libvirt-clients tzdata \
    && cd /tmp && apt-get download novnc && dpkg-deb -x novnc_*.deb novnc \
    && mv novnc/usr/share/novnc /usr/share/novnc && rm -rf novnc novnc_*.deb \
    && rm -rf /var/lib/apt/lists/* /usr/share/doc/* /usr/share/man/* \
    && echo "extension=libvirt-php.so" > /etc/php/8.5/mods-available/libvirt.ini \
    && phpenmod libvirt \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /app
COPY --from=vendor /app/vendor vendor
COPY --from=vendor /app/assets/css assets/css
COPY . .
RUN mkdir -p templates_c cache configs data/tokens \
    && chown -R www-data:www-data templates_c cache configs data \
    && chmod +x deploy/docker/entrypoint.sh

ENV LIBVIRT_URI=qemu:///system \
    PVM_USER=admin \
    PVM_READONLY=0 \
    HTTP_PORT=9080 \
    HTTPS_PORT=9443 \
    WS_PORT=6081

VOLUME ["/app/data", "/etc/nginx/ssl"]
ENTRYPOINT ["/app/deploy/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/app/deploy/docker/supervisord.conf"]
