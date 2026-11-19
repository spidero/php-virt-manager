{include file="header.tpl"}
<div class="card">
  <div class="card-header">{'New machine'|t}</div>
  <div class="card-body">
{if $errors}
    <div class="alert alert-danger">{foreach $errors as $e}{$e}<br>{/foreach}</div>
{/if}
{if !$pools}
    <div class="alert alert-warning">{'No active storage pool - start a pool first.'|t}</div>
{/if}
    <form method="post" action="create.php">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <div class="mb-3">
        <label class="form-label" for="os">{'Operating system'|t}</label>
        <select class="form-select" id="os" name="os" data-os-profile>
{foreach $os_profiles as $key => $p}
          <option value="{$key}"{if $form.os==$key} selected{/if} data-memory="{$p.memory}" data-disk="{$p.disk}" data-vcpus="{$p.vcpus}" data-firmware="{$p.firmware}" data-tpm="{$p.tpm}">{$p.label|t}</option>
{/foreach}
        </select>
        <div class="form-text">{'Fills in the recommended memory, disk, firmware and TPM. Windows also gets Hyper-V enlightenments and a local time clock.'|t}</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="name">{'Name'|t}</label>
        <input type="text" class="form-control" id="name" name="name" value="{$form.name}" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
      </div>
      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label" for="memory">{'Memory (MB)'|t}</label>
          <input type="number" class="form-control" id="memory" name="memory" value="{$form.memory}" min="256" max="{$max_memory_mb}" step="256" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="vcpus">{'vCPUs'|t}</label>
          <input type="number" class="form-control" id="vcpus" name="vcpus" value="{$form.vcpus}" min="1" max="{$max_vcpus}" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="disk">{'Disk (GB, qcow2)'|t}</label>
          <input type="number" class="form-control" id="disk" name="disk" value="{$form.disk}" min="1" max="4096" required>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="pool">{'Disk storage pool'|t}</label>
        <select class="form-select" id="pool" name="pool">
{foreach $pools as $name => $free}
          <option value="{$name}"{if $form.pool==$name} selected{/if}>{$name} ({$free})</option>
{/foreach}
        </select>
      </div>
      <fieldset class="mb-3">
        <legend class="form-label fs-6">{'Installation source'|t}</legend>
        <div class="form-check">
          <input type="radio" class="form-check-input" id="source-iso" name="source" value="iso"{if $form.source=='iso'} checked{/if}>
          <label class="form-check-label" for="source-iso">{'ISO image (CD/DVD)'|t}</label>
        </div>
        <div class="ms-4 mb-2">
          <select class="form-select form-select-sm" id="iso" name="iso" aria-label="{'Installation ISO'|t}">
            <option value="">- {'none'|t} -</option>
{foreach $isos as $path => $label}
            <option value="{$path}"{if $form.iso==$path} selected{/if}>{$label}</option>
{/foreach}
          </select>
          <div class="form-text">{'Another ISO can be downloaded from a URL on the storage pool page.'|t}</div>
        </div>
        <div class="ms-4 mb-2">
          <label class="form-label small" for="drivers">{'Windows drivers ISO (virtio-win, optional)'|t}</label>
          <select class="form-select form-select-sm" id="drivers" name="drivers">
            <option value="">- {'none: SATA disk and e1000e network card'|t} -</option>
{foreach $isos as $path => $label}
            <option value="{$path}"{if $form.drivers==$path} selected{/if}>{$label}</option>
{/foreach}
          </select>
          <div class="form-text">{'With the drivers ISO the disk and network card use virtio (faster): load the driver from the second CD in the installer. Download it on the storage pool page from'|t} <code class="user-select-all">{$virtio_win_url}</code></div>
        </div>
        <div class="form-check">
          <input type="radio" class="form-check-input" id="source-import" name="source" value="import"{if $form.source=='import'} checked{/if}{if !$free_volumes} disabled{/if}>
          <label class="form-check-label" for="source-import">{'Import an existing disk (no installation, disk size and pool are not used)'|t}</label>
        </div>
        <div class="ms-4 mb-2">
          <select class="form-select form-select-sm" name="volume" aria-label="{'Existing volume'|t}">
{foreach $free_volumes as $path => $v}
            <option value="{$path}"{if $form.volume==$path} selected{/if}>{$v.label}</option>
{foreachelse}
            <option value="">{'no unused volumes'|t}</option>
{/foreach}
          </select>
        </div>
        <div class="form-check">
          <input type="radio" class="form-check-input" id="source-pxe" name="source" value="pxe"{if $form.source=='pxe'} checked{/if}>
          <label class="form-check-label" for="source-pxe">{'Network boot (PXE) - needs a PXE server in the network'|t}</label>
        </div>
      </fieldset>
      <div class="mb-3">
        <label class="form-label" for="network">{'Network'|t}</label>
        <select class="form-select" id="network" name="network">
{foreach $networks as $target => $label}
          <option value="{$target}"{if $form.network==$target} selected{/if}>{$label}</option>
{/foreach}
        </select>
      </div>
      <div class="row">
        <div class="col-sm-6 mb-3">
          <label class="form-label" for="firmware">{'Firmware'|t}</label>
          <select class="form-select" id="firmware" name="firmware">
{foreach $firmwares as $fw => $label}
            <option value="{$fw}"{if $form.firmware==$fw} selected{/if}>{$label}</option>
{/foreach}
          </select>
          <div class="form-text">{'Windows 11 needs UEFI + Secure Boot and TPM. With UEFI, snapshots of a running machine are not possible (shut it down first).'|t}</div>
        </div>
        <div class="col-sm-6 mb-3">
          <div class="form-label">TPM</div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="tpm" name="tpm" value="1"{if $form.tpm} checked{/if}>
            <label class="form-check-label" for="tpm">{'TPM 2.0 (emulated)'|t}</label>
          </div>
        </div>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="start" name="start" value="1"{if $form.start} checked{/if}>
        <label class="form-check-label" for="start">{'Start after creation'|t}</label>
      </div>
      <p class="text-body-secondary"><small>{'q35, host-passthrough CPU, virtio disk and network, VNC graphics on localhost.'|t}</small></p>
      <button type="submit" class="btn btn-primary">{'Create'|t}</button>
    </form>
  </div>
</div>
{include file="footer.tpl"}
