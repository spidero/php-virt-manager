# php-virt-manager: nginx + php-fpm + websockify (noVNC) in one image.
# Needs the host libvirt socket and host networking (VNC listens on host localhost),
# see docker-compose.yml.
FROM ubuntu:26.04

ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx php8.5-fpm php8.5-cli php8.5-xml php8.5-mbstring php8.5-libvirt-php \
        novnc websockify imagemagick supervisor openssl ca-certificates composer unzip \
    && rm -rf /var/lib/apt/lists/* \
    && echo "extension=libvirt-php.so" > /etc/php/8.5/mods-available/libvirt.ini \
    && phpenmod libvirt \
    && rm -f /etc/nginx/sites-enabled/default

WORKDIR /app
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --no-scripts \
    && rm -rf /root/.cache/composer
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
