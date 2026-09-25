# Security policy

php-virt-manager controls virtual machines, usually on `qemu:///system`, so
vulnerabilities can affect the whole host. Reports are taken seriously.

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.0.x   | yes       |
| < 1.0   | no        |

Security fixes are released as new patch versions (1.0.1, ...).

## Reporting a vulnerability

**Do not open a public issue.** Report privately through GitHub:

1. Open [Report a vulnerability](https://github.com/spidero/php-virt-manager/security/advisories/new)
   (Security tab of the repository).
2. Describe the problem, the affected version or commit, the deployment
   (install.sh, Docker, other) and the steps to reproduce; a proof of concept
   helps.

You can expect an answer within 7 days. When the report is confirmed, a fix is
prepared in a private advisory, released, and the advisory is published with
credit to the reporter (unless you prefer to stay anonymous).

## Scope

In scope, for example:

- authentication or permission bypass (roles, API tokens, read-only
  connections)
- cross-site scripting, CSRF, injection into libvirt XML or shell commands
- access to files that must not be served (`config.php`, `data/`, `vendor/`)
- the console proxy (websockify tokens, `console-auth.php`)

Out of scope:

- deployments exposed to untrusted networks without HTTPS, or with the
  development server (`php -S`)
- vulnerabilities in libvirt, QEMU, PHP or other dependencies themselves -
  please report them to their projects (reports showing how php-virt-manager
  makes them exploitable are in scope)

## Hardening recommendations

- Serve the panel only over HTTPS (the nginx configuration in `deploy/` and
  the Docker image do this) and do not expose it to the internet without
  additional protection (VPN, IP allow list).
- Use strong passwords and give users the lowest role they need; use
  separate API tokens per tool and revoke unused ones.
- Keep `data/` and `config.php` readable only by the web server.
