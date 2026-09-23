# php-virt-manager

Simple web panel for libvirt/KVM: hypervisor info, virtual machine
start/stop/reboot/suspend and storage pool overview.

## Requirements

- PHP >= 7.4
- composer
- php-libvirt (Debian/Ubuntu package `php-libvirt-php`)
- access to libvirt (user running PHP must be in the `libvirt` group)

## Installation

```sh
composer install
mkdir -p templates_c configs cache
cp config-default.php config.php
```

Set login credentials in `config.php`:

```sh
php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
```

and paste the result into `$auth_password_hash`.

### Enabling the libvirt extension

On Ubuntu the `php8.x-libvirt-php` package ships only the `.so` file, without
an ini file, so the extension has to be enabled manually:

```sh
echo "extension=libvirt-php.so" | sudo tee /etc/php/8.5/mods-available/libvirt.ini
sudo phpenmod libvirt
```

## Running

Development server (localhost only):

```sh
php -S 127.0.0.1:8099
# or without enabling the extension globally:
php -d extension=libvirt-php -S 127.0.0.1:8099
```

For Apache, `.htaccess` blocks access to `vendor/`, `templates*/`, `config.php`,
`*.md` and other internal files (requires `AllowOverride All`).

## Security notes

- The panel controls VMs on `qemu:///system` - do not expose it to untrusted
  networks, use HTTPS when not on localhost.
- VM actions are POST requests protected by a CSRF token.
- Set `$readonly = 1` in `config.php` for a view-only panel.
