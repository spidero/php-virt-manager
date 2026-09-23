# php-virt-manager

Web panel for libvirt/KVM written in PHP (Smarty 4, Bootstrap 4).

## Features

- hypervisor overview, machine list with state indicators
- machine details: memory, vCPUs, disks, network interfaces with guest IPs,
  autostart, live screen preview
- power actions: start, shutdown, reboot, pause/resume, force stop
- snapshots: create, revert, delete
- browser console (noVNC via websockify), SPICE to VNC graphics switch
- new machine wizard (qcow2 disk in any active pool, ISO from any pool, network)
- storage pools with volumes, libvirt networks with DHCP leases
- login with lockout after repeated failures, CSRF protection, read-only mode
- action log (logins, power actions, snapshots, created machines)

## Requirements

- PHP >= 8.0 with the libvirt extension (Debian/Ubuntu: `php-libvirt-php`)
- composer
- access to the libvirt socket (the PHP user must be in the `libvirt` group)
- for the browser console: nginx, websockify and noVNC (see Deployment)

## Quick start (development)

```sh
composer install
mkdir -p templates_c configs cache
cp config-default.php config.php
php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
# paste the hash into $auth_password_hash in config.php
php -d extension=libvirt-php -S 127.0.0.1:8099
```

The built-in server is for development only; the browser console is not
available there.

### Enabling the libvirt extension

On Ubuntu the `php8.x-libvirt-php` package ships only the `.so` file without
an ini file:

```sh
echo "extension=libvirt-php.so" | sudo tee /etc/php/8.5/mods-available/libvirt.ini
sudo phpenmod libvirt
```

## Deployment

### nginx on the host

```sh
sudo deploy/install.sh
```

The script installs nginx, php-fpm, websockify and noVNC, copies the panel to
`/var/www/php-virt-manager`, creates `config.php` with a random password
(printed at the end), a self-signed TLS certificate, a dedicated php-fpm pool
running with the `libvirt` group and a systemd unit for websockify.

Defaults: HTTPS on port 8443 (HTTP 8090 redirects), websockify on
127.0.0.1:6080. Override with environment variables `APP_DIR`, `HTTPS_PORT`,
`HTTP_PORT`, `WS_PORT`, `PHP_VERSION`. Re-running the script updates the
application and keeps `config.php`, the certificate and `data/`.

### Docker

```sh
docker compose up -d --build
docker compose logs | grep login     # generated password, unless PVM_PASSWORD is set
```

The container uses host networking (VNC consoles listen on the host's
127.0.0.1) and the host libvirt socket (`/var/run/libvirt`); it joins the
socket's group automatically. Panel: `https://<host>:9443/`.

| Variable            | Default          | Description                              |
|---------------------|------------------|------------------------------------------|
| `LIBVIRT_URI`       | `qemu:///system` | libvirt connection                       |
| `PVM_USER`          | `admin`          | login name                               |
| `PVM_PASSWORD`      | random           | login password (or `PVM_PASSWORD_HASH`)  |
| `PVM_READONLY`      | `0`              | `1` = view only                          |
| `HTTPS_PORT`        | `9443`           | HTTPS port                               |
| `HTTP_PORT`         | `9080`           | HTTP port (redirects to HTTPS)           |
| `WS_PORT`           | `6081`           | websockify port on 127.0.0.1             |

Volumes: `/app/data` (action log, login lock data, console tokens),
`/etc/nginx/ssl` (`cert.pem`, `key.pem` - self-signed generated if missing).

### Apache

`.htaccess` blocks internal files and directories (requires
`AllowOverride All`). The browser console needs a websocket proxy and is
supported only with the nginx configuration from `deploy/`.

## Configuration

See `config-default.php`: libvirt URI, read-only mode, credentials, login
lockout (`$login_max_attempts`, `$login_lock_seconds`), data directory and
console settings.

## Console

The console works for machines with VNC graphics. Machines using SPICE show a
"switch to VNC" action which rewrites the persistent definition (SPICE agent
channel and USB redirection are removed); the change takes effect after the
machine is shut down and started again. Each console opening creates a
one-hour token; the websocket proxy additionally requires a logged in session.

## Development

```sh
composer install
vendor/bin/phpstan analyse
```

`stubs/libvirt.stub.php` declares the libvirt extension API for static
analysis. CI (GitHub Actions) runs lint, PHPStan and a Docker image build.

## Security notes

- The panel controls machines on `qemu:///system`; use HTTPS and a strong
  password, do not expose it to untrusted networks.
- VNC listens on 127.0.0.1 only; remote access goes through the authenticated
  websocket proxy.
