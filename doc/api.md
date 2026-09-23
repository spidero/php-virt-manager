# REST API

php-virt-manager exposes a JSON REST API for scripts, CI pipelines and
configuration management tools (Ansible, Terraform external providers, cron
jobs). Everything the API does is also possible in the web panel and is
recorded in the same action log.

- Base URL: `https://<panel>/api.php/v1`
- Format: JSON requests and responses (`Content-Type: application/json`)
- Authentication: personal tokens, `Authorization: Bearer pvm_...`
- Machine-readable specification: [openapi.yaml](openapi.yaml) (OpenAPI 3.1,
  usable with Swagger UI, Postman, Insomnia or client generators)

## Contents

- [Authentication](#authentication)
- [Permissions](#permissions)
- [Connections (hypervisors)](#connections-hypervisors)
- [Errors](#errors)
- [Endpoints](#endpoints)
  - [Connections](#get-connections)
  - [Machines](#get-domains)
  - [Power actions](#post-domainsnameactionsaction)
  - [Snapshots](#snapshots)
  - [Clone](#post-domainsnameclone)
  - [Cloud images](#cloud-images)
  - [Background jobs](#background-jobs)
  - [Networks and storage pools](#networks-and-storage-pools)
- [Examples](#examples)
- [Security notes](#security-notes)

## Authentication

1. Log in to the panel and open your **profile** (click your user name in the
   menu).
2. In **API tokens**, enter a name (for example `ansible` or `ci`) and click
   **Create token**.
3. Copy the token (`pvm_` followed by 40 hexadecimal characters). It is shown
   only once; the panel stores only its SHA-256 hash. If it is lost, revoke it
   and create a new one.

Send the token in every request:

```sh
export PVM_URL=https://panel.example.com:8443/api.php/v1
export PVM_TOKEN=pvm_0123456789abcdef0123456789abcdef01234567

curl -H "Authorization: Bearer $PVM_TOKEN" $PVM_URL/domains
```

Tokens are revoked in the profile and are deleted together with their user.
Each token shows when it was last used.

Failed authentications (missing, unknown or revoked token) count towards the
same per-IP lockout as failed panel logins: after `$login_max_attempts`
failures (default 5) the address is blocked for `$login_lock_seconds`
(default 15 minutes) and the API answers `429`.

## Permissions

A token acts with the **current role of its owner**; changing a user's role
changes what their tokens may do immediately.

| Role       | Allowed                                                        |
|------------|----------------------------------------------------------------|
| `viewer`   | all `GET` endpoints except background jobs                     |
| `operator` | everything in this API (power actions, snapshots, cloning, creating machines, jobs) |
| `admin`    | same as operator (administration such as users or storage is done in the panel) |

Additionally, a connection configured as read-only (`'readonly' => 1` in
`$connections`) rejects every state-changing request with `403`.

## Connections (hypervisors)

When the panel manages several hypervisors (`$connections` in `config.php`),
select one with the `conn` query parameter. Without it the first connection
is used.

```sh
curl -H "Authorization: Bearer $PVM_TOKEN" "$PVM_URL/domains?conn=lab"
```

The keys are listed by [`GET /connections`](#get-connections).

## Errors

Errors use HTTP status codes and a JSON body with a message:

```json
{"error": "machine not found"}
```

| Status | Meaning |
|--------|---------|
| 400 | Invalid request: bad JSON, invalid name, validation errors (all messages joined in `error`) |
| 401 | Missing or invalid token |
| 403 | The role does not allow the endpoint, or the connection is read-only |
| 404 | Unknown endpoint, machine, snapshot, job or connection |
| 405 | Wrong HTTP method for the path (the message lists the allowed one) |
| 409 | Conflict: libvirt refused the operation (message from libvirt), the machine must be shut off, or the name already exists |
| 429 | Too many failed authentications from this IP address |
| 502 | The panel cannot connect to the hypervisor |

Machine names may contain spaces; encode them in URLs (`azure%20linux`).

## Endpoints

All paths are relative to the base URL `…/api.php/v1`.

### GET /connections

Configured hypervisors. `current` marks the one used by this request.

```json
[
  {"key": "default", "label": "qemu:///system", "uri": "qemu:///system", "readonly": false, "current": true}
]
```

### GET /domains

Machines with their state.

```json
[
  {"name": "azure linux", "state": "running", "state_id": 1},
  {"name": "web-01", "state": "shut off", "state_id": 5}
]
```

`state` is one of `running`, `paused`, `shut off`, `shutting down`,
`crashed`, `suspended`, `blocked`, `no state`. `state_id` is the libvirt
`virDomainState` value (1 running, 3 paused, 5 shut off).

### GET /domains/{name}

Details of one machine. `interfaces[].ips` is filled when the machine has a
DHCP lease on a libvirt network or runs the QEMU guest agent (machines
created from cloud images have it).

```json
{
  "name": "web-01",
  "uuid": "51796b60-d5ec-4368-81b3-1b23a5e6a371",
  "state": "running",
  "memory_mb": 2048,
  "vcpus": 2,
  "autostart": true,
  "disks": [
    {"device": "disk", "target": "vda", "bus": "virtio",
     "source": "/var/lib/libvirt/images/web-01.qcow2", "size": "20 GB (used 1.8 GB)"},
    {"device": "cdrom", "target": "sda", "bus": "sata",
     "source": "/var/lib/libvirt/images/web-01-seed.iso", "size": ""}
  ],
  "interfaces": [
    {"type": "network", "source": "default", "mac": "52:54:00:31:4b:82",
     "model": "virtio", "target": "vnet13", "ips": ["192.168.122.73/24"]}
  ],
  "graphics": {"type": "vnc", "port": 5901, "listen": "127.0.0.1"}
}
```

### GET /domains/{name}/stats

Raw cumulative counters. Compute rates from two samples:
`(counter2 - counter1) / (time2 - time1)`. The counters are meaningful only
while `state` is 1 (running); for a shut off machine (`state` 5) they are
left over from libvirt and must be ignored. Counters start again from zero
when the machine is restarted, so treat a negative difference as zero.

```json
{
  "time": 1790191530.04976,
  "counters": {
    "state": 1,
    "cpu_ns": 123386577000,
    "vcpus": 2,
    "mem_rss": 54900,
    "mem_total": 4194304,
    "disk_rd": 4608,
    "disk_wr": 0,
    "net_rx": 90,
    "net_tx": 0
  }
}
```

| Field | Unit | Meaning |
|-------|------|---------|
| `time` | seconds (Unix, fractional) | time of the sample |
| `cpu_ns` | nanoseconds | CPU time used by all vCPUs; CPU % = Δcpu_ns / Δtime / 1e9 / vcpus × 100 |
| `vcpus` | count | active vCPUs |
| `mem_rss` | KiB | memory used by the QEMU process on the host |
| `mem_total` | KiB | memory assigned to the guest |
| `disk_rd`, `disk_wr` | bytes | read/written, sum of all disks |
| `net_rx`, `net_tx` | bytes | received/sent, sum of all interfaces |

### POST /domains/{name}/actions/{action}

Power actions (`operator`). No request body.

| Action | Effect |
|--------|--------|
| `start` | start a shut off machine |
| `stop` | ACPI shutdown (the guest must react to it) |
| `destroy` | force stop, like pulling the power cable |
| `reboot` | ACPI reboot |
| `suspend` | pause (the machine keeps its memory) |
| `resume` | continue a paused machine |

```sh
curl -X POST -H "Authorization: Bearer $PVM_TOKEN" $PVM_URL/domains/web-01/actions/start
```

```json
{"ok": true, "message": "Starting machine, it may take some time"}
```

An action that is not possible in the current state (starting a running
machine) returns `409` with the libvirt message.

### Snapshots

#### GET /domains/{name}/snapshots

Newest first. `created` is a Unix timestamp, `state` is the machine state
stored in the snapshot. Automatic snapshots of schedules are named
`auto-<schedule id>-<YYYYmmdd-HHMMSS>`.

```json
[
  {"name": "before-upgrade", "description": "manual", "state": "running", "created": 1790182486}
]
```

#### POST /domains/{name}/snapshots

`operator`. Body (both fields optional; the default name is the current date
and time):

```json
{"name": "before-upgrade", "description": "before apt full-upgrade"}
```

Names: 1-64 characters, letters, digits, `.`, `_`, `-`, starting with a letter
or digit. Response: `{"ok": true, "name": "before-upgrade"}`.

#### POST /domains/{name}/snapshots/{snapshot}/revert

`operator`. Returns the machine to the snapshot; the current state is lost.
Response: `{"ok": true}`.

#### DELETE /domains/{name}/snapshots/{snapshot}

`operator`. Response: `{"ok": true}`.

### POST /domains/{name}/clone

`operator`. The source machine must be shut off. Disks are copied in the
background; the response contains the [job](#background-jobs) id.

```json
{"name": "web-02"}
```

```json
{"ok": true, "job": 12}
```

The clone gets a new UUID, new MAC addresses and automatic VNC/SPICE ports;
CD/DVD images stay shared.

### Cloud images

#### GET /cloud/images

The image catalog and whether each image is downloaded (images are
downloaded by an administrator in the panel).

```json
[
  {"key": "ubuntu-26.04", "label": "Ubuntu 26.04 LTS", "downloaded": false, "pool": null},
  {"key": "debian-13", "label": "Debian 13", "downloaded": true, "pool": "default"}
]
```

#### POST /cloud

`operator`. Creates a machine from a downloaded image with cloud-init; runs
as a background job.

```json
{
  "image": "debian-13",
  "name": "ci-runner-1",
  "memory": 2048,
  "vcpus": 2,
  "disk": 20,
  "pool": "default",
  "network": "default",
  "user": "admin",
  "ssh_keys": ["ssh-ed25519 AAAAC3Nza... me@laptop"],
  "password": "optional-password",
  "start": true
}
```

| Field | Required | Default | Notes |
|-------|----------|---------|-------|
| `image` | yes | | key from `GET /cloud/images`, must be downloaded |
| `name` | yes | | machine name, also the guest hostname |
| `memory` | no | 2048 | MB, 256 up to the host memory |
| `vcpus` | no | 1 | up to the host CPU count |
| `disk` | no | 20 | GB, 3-4096; the image is grown to this size |
| `pool` | no | first active pool | storage pool for the disk |
| `network` | no | `default` | libvirt network |
| `user` | no | `admin` | user created in the guest, gets sudo without a password |
| `ssh_keys` | one of `ssh_keys`/`password` | | list of public keys (or a string with one key per line) |
| `password` | one of `ssh_keys`/`password` | | at least 8 characters; enables password login over SSH |
| `start` | no | false | start the machine when it is created |

Response: `{"ok": true, "job": 13}`. Validation problems return `400` with
all messages in `error`.

### Background jobs

Long operations (cloning, creating machines from cloud images, image
downloads started in the panel) run in the background. They are executed by
`bin/cron.php` (a systemd timer or the Docker image); without it jobs stay
`queued`.

#### GET /jobs

The last 100 jobs, newest first. `operator`.

#### GET /jobs/{id}

```json
{
  "id": 13,
  "type": "cloud_create",
  "target": "ci-runner-1",
  "status": "running",
  "progress": null,
  "message": "copying the base image",
  "user": "admin",
  "created_at": 1790187502,
  "finished_at": null
}
```

| Field | Meaning |
|-------|---------|
| `type` | `clone`, `cloud_create`, `image_download` |
| `status` | `queued`, `running`, `done`, `failed` |
| `progress` | percent of the current phase, `null` when unknown or finished |
| `message` | current phase while running; result or error when finished |

Poll every few seconds until `status` is `done` or `failed`.

### Networks and storage pools

#### GET /networks

```json
[{"name": "default", "active": true, "ip_range": "192.168.122.0/24"}]
```

#### GET /pools

Sizes in bytes.

```json
[{"name": "default", "active": true, "capacity": 1006979764224,
  "allocation": 627114065920, "available": 379865698304}]
```

## Examples

### Snapshot before maintenance, revert on failure

```sh
#!/bin/sh
set -e
api() { curl -sf -H "Authorization: Bearer $PVM_TOKEN" -H 'Content-Type: application/json' "$@"; }

api -X POST $PVM_URL/domains/web-01/snapshots -d '{"name": "before-upgrade"}'
if ! ssh admin@web-01 'sudo apt-get -y full-upgrade'; then
    api -X POST $PVM_URL/domains/web-01/snapshots/before-upgrade/revert
fi
```

### Create a machine from a cloud image and wait for its IP address

```sh
#!/bin/sh
set -e
api() { curl -sf -H "Authorization: Bearer $PVM_TOKEN" -H 'Content-Type: application/json' "$@"; }

job=$(api -X POST $PVM_URL/cloud -d "{
  \"image\": \"debian-13\", \"name\": \"ci-runner-1\", \"memory\": 2048, \"vcpus\": 2,
  \"disk\": 20, \"ssh_keys\": [\"$(cat ~/.ssh/id_ed25519.pub)\"], \"start\": true
}" | jq -r .job)

# wait for the background job
while :; do
    status=$(api $PVM_URL/jobs/$job | jq -r .status)
    [ "$status" = done ] && break
    [ "$status" = failed ] && { api $PVM_URL/jobs/$job | jq -r .message; exit 1; }
    sleep 3
done

# wait for the DHCP lease / guest agent
until ip=$(api $PVM_URL/domains/ci-runner-1 | jq -er '.interfaces[0].ips[0] // empty'); do
    sleep 5
done
echo "ssh admin@${ip%/*}"
```

### Python

```python
import os
import requests

session = requests.Session()
session.headers["Authorization"] = "Bearer " + os.environ["PVM_TOKEN"]
url = os.environ["PVM_URL"]

for machine in session.get(f"{url}/domains").json():
    print(f"{machine['name']:30} {machine['state']}")

response = session.post(f"{url}/domains/web-01/actions/reboot")
if not response.ok:
    raise SystemExit(response.json()["error"])
```

With a self-signed certificate, pass the certificate file
(`requests.get(..., verify="cert.pem")`, `curl --cacert cert.pem`) rather
than disabling verification.

### Ansible

```yaml
- name: Snapshot web-01 before deployment
  ansible.builtin.uri:
    url: "{{ pvm_url }}/domains/web-01/snapshots"
    method: POST
    headers:
      Authorization: "Bearer {{ pvm_token }}"
    body_format: json
    body:
      name: "deploy-{{ ansible_date_time.epoch }}"
      description: "before deployment"
  delegate_to: localhost
```

## Security notes

- Always use HTTPS; the token is a bearer credential.
- Create a separate token per tool or pipeline, so it can be revoked on its
  own, and give its owner the lowest role that is enough (`viewer` for
  monitoring).
- Every state-changing call is written to the action log with the user,
  the IP address and the suffix `(api)`.
- The API does not use the panel session or cookies, so CSRF protection is
  not needed; browsers cannot send the `Authorization` header cross-site
  without CORS, which the panel does not enable.
