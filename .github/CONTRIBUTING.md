# Contributing to php-virt-manager

Thank you for helping. Bug reports, translations, documentation and code are
all welcome. By taking part you agree to follow the
[code of conduct](CODE_OF_CONDUCT.md).

## Reporting bugs and requesting features

Use the [issue forms](https://github.com/spidero/php-virt-manager/issues/new/choose).
For a bug, include the version (or commit), how the panel is deployed
(install.sh, Docker, development server), the libvirt/QEMU version and the
steps to reproduce.

**Security issues must not be reported in public issues** - see the
[security policy](SECURITY.md).

## Development setup

Requirements: PHP 8.2+ with the libvirt, SQLite and curl extensions, composer,
a local libvirt (`qemu:///system`) and a user in the `libvirt` group.

```sh
git clone https://github.com/spidero/php-virt-manager.git
cd php-virt-manager
composer install
mkdir -p templates_c configs cache
cp config-default.php config.php
php -r 'echo password_hash("admin", PASSWORD_DEFAULT), PHP_EOL;'   # paste into $auth_password_hash

# two terminals:
php -d extension=libvirt-php -S 127.0.0.1:8099
php -d extension=libvirt-php bin/cron.php --loop                   # background jobs
```

The browser console needs nginx and websockify; use the Docker image
(`docker compose up -d --build`) to work on it.

**Test on throwaway machines.** Create test VMs, pools and networks with a
recognizable prefix and remove them afterwards. When deleting a test VM with
virsh, delete only its own disks (`virsh undefine NAME --storage vda`), never
`--remove-all-storage`, which also deletes shared ISO images.

## Before opening a pull request

```sh
vendor/bin/phpunit                  # unit tests
vendor/bin/phpstan analyse          # static analysis, level 5
for lang in de es nb pl uk; do php bin/i18n-strings.php $lang; done   # missing translations
```

CI runs the same checks on PHP 8.2 and 8.5 and builds the Docker image.

## Code conventions

- Plain PHP without a framework, Smarty 5 templates, Bootstrap 5.3.
- Follow the style of the surrounding code; comments in English.
- Output goes through templates only: Smarty escapes every variable
  (`escape_html`). Never put variables into inline JavaScript - use `data-*`
  attributes.
- State-changing requests are POST forms with `csrf_require()`, followed by a
  redirect and a flash message; check permissions with `readonly_guard()` or
  `require_permission()`.
- Validate machine, pool and network names against the `libvirt_list_*` lists
  before looking them up.
- Keep XML transformations as pure functions in `lib/vmedit.php` or
  `lib/domain.php` and cover them with tests.
- Operations that can take longer than a few seconds run as background jobs
  (`lib/jobs.php`, `lib/jobhandlers.php`) and report progress.
- libvirt-php 0.5.x: release snapshot resources (`unset($snap)`) while the
  domain resource is still alive, and do not use `libvirt_stream_send()`; see
  the comments in `lib/domain.php` and `lib/cloud.php`.

## Translations

Interface texts are written in English and translated with `t('...')` in PHP
and `{'...'|t}` in templates. Every new text needs a translation in all
language files `lang/<code>.php` (de, es, nb, pl, uk); `%s`/`%d` placeholders
must stay in the same order. The tests fail when a translation is missing.

To add a language, create `lang/<code>.php` with all keys from
`php bin/i18n-strings.php` and add the language to `LANGUAGES` in
`lib/i18n.php`.

Native speakers reviewing existing translations are very welcome.

## REST API changes

Update `doc/api.md` and `doc/openapi.yaml` together with `lib/api.php`; a test
checks that both documents list exactly the routes of the code.

## Commits and pull requests

- One topic per pull request; describe what and why.
- Write commit messages in English, imperative mood ("Add ...", "Fix ...").
- New features need tests where the logic can be tested without libvirt.

By contributing you agree that your contributions are licensed under the
GPL-3.0-or-later license of the project.
