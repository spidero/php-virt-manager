{include file="header.tpl"}
{function name=form_start action=''}
<form method="post" action="edit.php">
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="node" value="{$node}">
  <input type="hidden" name="action" value="{$action}">
{/function}
<h5 class="mb-3">{'Edit %s'|t:$node} <a href="node.php?node={$node|escape:'url'}" class="btn btn-link btn-sm">&laquo; {'back'|t}</a>
{if $can.admin}  <a href="xml.php?node={$node|escape:'url'}" class="btn btn-outline-secondary btn-sm float-end">{'Edit XML'|t}</a>{/if}</h5>
{if $active}
<div class="alert alert-info">{'The machine is running: memory, vCPU and boot order changes take effect after it is shut down and started again. Disks and network interfaces are attached immediately.'|t}</div>
{/if}

<div class="card mb-3">
  <div class="card-header">{'Disks'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead class="small"><tr><th>{'Device'|t}</th><th>{'File'|t}</th><th>{'Size'|t}</th><th>{'Resize'|t}</th><th></th></tr></thead>
    <tbody>
{foreach $device_disks as $d}
      <tr>
        <td><code>{$d.target}</code> <small class="text-body-secondary">{$d.bus}</small></td>
        <td class="small text-break">{$d.path}{if $d.shared} <span class="badge text-bg-warning">{'shared'|t}</span>{/if}</td>
        <td class="text-nowrap">{$d.capacity}</td>
        <td>
          <form method="post" action="edit.php" class="d-flex gap-1" style="max-width: 14rem;">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="node" value="{$node}">
            <input type="hidden" name="action" value="resize_disk">
            <input type="hidden" name="target" value="{$d.target}">
            <input type="number" name="size" class="form-control form-control-sm" min="{$d.capacity_gb+1}" max="65536" value="{$d.capacity_gb+10}" aria-label="{'New size (GB)'|t}" required>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{'GB, resize'|t}</button>
          </form>
        </td>
        <td class="text-end">
          <form method="post" action="edit.php" class="d-inline-flex gap-2 align-items-center" data-confirm="{'Detach disk %s from the machine?'|t:$d.target}">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="node" value="{$node}">
            <input type="hidden" name="action" value="detach_disk">
            <input type="hidden" name="target" value="{$d.target}">
{if !$active && $can.admin && !$d.shared}
            <label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="delete_volume" value="1"> {'delete file'|t}</label>
{/if}
            <button type="submit" class="btn btn-outline-danger btn-sm">{'Detach'|t}</button>
          </form>
        </td>
      </tr>
{foreachelse}
      <tr><td colspan="5" class="text-body-secondary">{'No disks'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">{'Network interfaces'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead class="small"><tr><th>MAC</th><th>{'Network'|t} / {'Model'|t}</th><th></th></tr></thead>
    <tbody>
{foreach $device_nics as $mac => $n}
      <tr>
        <td><code>{$mac}</code></td>
        <td>
{if $n.type == 'network' || $n.type == 'bridge' || $n.type == 'direct'}
          <form method="post" action="edit.php" class="d-flex gap-1 flex-wrap">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="node" value="{$node}">
            <input type="hidden" name="action" value="nic_change">
            <input type="hidden" name="mac" value="{$mac}">
            <select name="network" class="form-select form-select-sm w-auto" aria-label="{'Network'|t}">
{foreach $networks as $target => $label}
              <option value="{$target}"{if $target==$n.target} selected{/if}>{$label}</option>
{/foreach}
{if !isset($networks[$n.target])}
              <option value="" selected disabled>{$n.type}: {$n.source}</option>
{/if}
            </select>
            <select name="model" class="form-select form-select-sm w-auto" aria-label="{'Model'|t}">
{foreach $nic_models as $m => $label}
              <option value="{$m}"{if $m==$n.model} selected{/if}>{$label}</option>
{/foreach}
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm">{'Save'|t}</button>
          </form>
{else}
          <span class="small">{$n.type}: {$n.source} ({$n.model})</span>
{/if}
        </td>
        <td class="text-end">
          <form method="post" action="edit.php" class="d-inline" data-confirm="{'Detach network interface %s?'|t:$mac}">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="node" value="{$node}">
            <input type="hidden" name="action" value="detach_nic">
            <input type="hidden" name="mac" value="{$mac}">
            <button type="submit" class="btn btn-outline-danger btn-sm">{'Detach'|t}</button>
          </form>
        </td>
      </tr>
{foreachelse}
      <tr><td colspan="3" class="text-body-secondary">{'No network interfaces'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>

{if $can.admin}
<div class="card mb-3">
  <div class="card-header">{'Host devices (USB, PCI)'|t}</div>
  <div class="card-body">
    <div class="alert alert-warning small py-2">{'A passed through device belongs to the machine while it runs: the host loses it (a USB keyboard stops working on the host, a PCI disk controller or network card can take the host down). PCI needs IOMMU (VT-d/AMD-Vi) and takes effect at the next start.'|t}</div>
    <table class="table table-sm align-middle">
      <tbody>
{foreach $vm_hostdevs as $d}
        <tr>
          <td><code>{$d.key}</code></td><td>{$d.label}</td>
          <td class="text-end">
            <form method="post" action="edit.php" class="d-inline" data-confirm="{'Detach host device %s?'|t:$d.label}">
              <input type="hidden" name="csrf" value="{$csrf_token}">
              <input type="hidden" name="node" value="{$node}">
              <input type="hidden" name="action" value="hostdev_detach">
              <input type="hidden" name="device" value="{$d.key}">
              <button type="submit" class="btn btn-outline-danger btn-sm">{'Detach'|t}</button>
            </form>
          </td>
        </tr>
{foreachelse}
        <tr><td class="text-body-secondary">{'No host devices attached.'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
    <form method="post" action="edit.php" class="d-flex flex-wrap gap-1" data-confirm="{'Attach the selected host device to %s? The host loses it while the machine runs.'|t:$node}">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="node" value="{$node}">
      <input type="hidden" name="action" value="hostdev_attach">
      <select name="device" class="form-select form-select-sm w-auto mw-100" aria-label="{'Host device'|t}" required>
        <option value="">- {'select a device'|t} -</option>
        <optgroup label="USB">
{foreach $host_devices as $key => $d}{if $d.type=='usb'}
          <option value="{$key}">{$d.label} ({$d.detail})</option>
{/if}{/foreach}
        </optgroup>
        <optgroup label="PCI">
{foreach $host_devices as $key => $d}{if $d.type=='pci'}
          <option value="{$key}">{$d.label} ({$d.detail})</option>
{/if}{/foreach}
        </optgroup>
      </select>
      <button type="submit" class="btn btn-outline-primary btn-sm">{'Attach'|t}</button>
    </form>
  </div>
</div>
{/if}

<div class="row">
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Memory and vCPUs'|t}</div>
      <div class="card-body">
        {call form_start action='resources'}
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label" for="memory">{'Maximum memory (MB)'|t}</label>
              <input type="number" class="form-control" id="memory" name="memory" value="{$memory_mb}" min="128" max="{$max_memory_mb}" step="128" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label" for="current_memory">{'Current memory (MB)'|t}</label>
              <input type="number" class="form-control" id="current_memory" name="current_memory" value="{$current_memory_mb}" min="128" max="{$max_memory_mb}" step="128" required>
            </div>
            <div class="col-12 form-text mt-n2 mb-3">{'Current memory (balloon) can be lowered or raised up to the maximum while the machine runs; the guest needs the virtio balloon driver.'|t}</div>
            <div class="col-4 mb-3">
              <label class="form-label" for="sockets">{'Sockets'|t}</label>
              <input type="number" class="form-control" id="sockets" name="sockets" value="{$cpu.sockets}" min="1" max="{$max_vcpus}" required>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label" for="cores">{'Cores'|t}</label>
              <input type="number" class="form-control" id="cores" name="cores" value="{$cpu.cores}" min="1" max="{$max_vcpus}" required>
            </div>
            <div class="col-4 mb-3">
              <label class="form-label" for="threads">{'Threads'|t}</label>
              <input type="number" class="form-control" id="threads" name="threads" value="{$cpu.threads}" min="1" max="{$max_vcpus}" required>
            </div>
            <div class="col-12 form-text mt-n2 mb-3">{'vCPUs = sockets x cores x threads (now %d, host has %d). Desktop Windows uses at most 2 sockets.'|t:$vcpus:$max_vcpus}</div>
            <div class="col-12 mb-3">
              <label class="form-label" for="cpu_mode">{'CPU model'|t}</label>
              <select class="form-select" id="cpu_mode" name="cpu_mode">
{foreach $cpu_modes as $m => $label}
                <option value="{$m}"{if $cpu.mode==$m} selected{/if}>{$label}</option>
{/foreach}
{if !isset($cpu_modes[$cpu.mode])}
                <option value="{$cpu.mode}" selected>{$cpu.mode}</option>
{/if}
              </select>
              <div class="form-text">{'host-passthrough: all features of the host CPU (fastest). host-model: a CPU model close to the host, easier to move to other hosts.'|t}</div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">{'Save'|t}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'CD/DVD'|t}</div>
      <div class="card-body">
        {call form_start action='cdrom'}
          <div class="mb-3">
            <label class="form-label" for="iso">{'Medium'|t}</label>
            <select class="form-select" id="iso" name="iso">
              <option value="">- {'empty (eject)'|t} -</option>
{foreach $isos as $path => $label}
              <option value="{$path}"{if $cdrom_iso==$path} selected{/if}>{$label}</option>
{/foreach}
            </select>
{if $cdrom_iso===null}
            <div class="form-text">{'The machine has no CD/DVD drive, a SATA drive will be added.'|t}</div>
{/if}
          </div>
          <button type="submit" class="btn btn-primary">{'Save'|t}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Add disk'|t}</div>
      <div class="card-body">
        {call form_start action='add_disk'}
          <div class="row">
            <div class="col-sm-4 mb-3">
              <label class="form-label" for="size">{'Size (GB)'|t}</label>
              <input type="number" class="form-control" id="size" name="size" value="10" min="1" max="4096" required>
            </div>
            <div class="col-sm-8 mb-3">
              <label class="form-label" for="pool">{'Storage pool'|t}</label>
              <select class="form-select" id="pool" name="pool">
{foreach $pools as $name => $free}
                <option value="{$name}">{$name} ({$free})</option>
{/foreach}
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">{'Add disk'|t}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Add network interface'|t}</div>
      <div class="card-body">
        {call form_start action='add_nic'}
          <div class="mb-3">
            <label class="form-label" for="network">{'Network'|t}</label>
            <select class="form-select" id="network" name="network">
{foreach $networks as $target => $label}
              <option value="{$target}">{$label}</option>
{/foreach}
            </select>
          </div>
          <button type="submit" class="btn btn-primary">{'Add network interface'|t}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Text console'|t}</div>
      <div class="card-body">
        <p class="small text-body-secondary">{'A serial console in the browser (login prompt, boot messages, emergency shell); the guest needs a console on ttyS0, which cloud images have.'|t}</p>
        {call form_start action='serial_console'}
{if $serial_port}
          <p class="mb-2"><span class="badge text-bg-success">{'enabled'|t}</span> <small class="text-body-secondary">127.0.0.1:{$serial_port}</small></p>
          <button type="submit" class="btn btn-outline-secondary">{'Disable'|t}</button>
{else}
          <input type="hidden" name="enable" value="1">
          <button type="submit" class="btn btn-primary">{'Enable'|t}</button>
{/if}
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Boot order'|t}</div>
      <div class="card-body">
        {call form_start action='boot'}
{foreach [0,1,2] as $i}
          <div class="input-group input-group-sm mb-2">
            <label class="input-group-text" for="boot{$i}">{$i+1}.</label>
            <select class="form-select" id="boot{$i}" name="boot[]">
              <option value="">-</option>
{foreach ['hd' => 'Hard disk', 'cdrom' => 'CD/DVD', 'network' => 'Network (PXE)'] as $dev => $label}
              <option value="{$dev}"{if ($boot[$i]|default:'')==$dev} selected{/if}>{$label|t}</option>
{/foreach}
            </select>
          </div>
{/foreach}
          <button type="submit" class="btn btn-primary">{'Save'|t}</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Rename'|t}</div>
      <div class="card-body">
{if $active}
        <p class="text-body-secondary mb-0">{'Shut the machine down to rename it.'|t}</p>
{else}
        {call form_start action='rename'}
          <div class="mb-3">
            <label class="form-label" for="new_name">{'New name'|t}</label>
            <input type="text" class="form-control" id="new_name" name="name" value="{$node}" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
            <div class="form-text">{'Disk files keep their names; starred machines and snapshot schedules follow the new name.'|t}</div>
          </div>
          <button type="submit" class="btn btn-primary">{'Rename'|t}</button>
        </form>
{/if}
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Clone'|t}</div>
      <div class="card-body">
{if $active}
        <p class="text-body-secondary mb-0">{'Shut the machine down to clone it.'|t}</p>
{else}
        {call form_start action='clone'}
          <div class="mb-3">
            <label class="form-label" for="clone_name">{'Name of the copy'|t}</label>
            <input type="text" class="form-control" id="clone_name" name="name" value="{$node}-clone" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
            <div class="form-text">{'Disks are copied in the background; CD/DVD images are shared.'|t}</div>
          </div>
          <button type="submit" class="btn btn-primary">{'Clone'|t}</button>
        </form>
{/if}
      </div>
    </div>
  </div>
</div>

{if $can.admin}
<div class="card border-danger mb-3">
  <div class="card-header text-danger">{'Delete machine'|t}</div>
  <div class="card-body">
{if $active}
    <p class="text-body-secondary mb-0">{'Shut the machine down to delete it.'|t}</p>
{else}
    <form method="post" action="edit.php" data-confirm="{'Delete machine %s? This cannot be undone.'|t:$node}">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="node" value="{$node}">
      <input type="hidden" name="action" value="delete">
      <p class="mb-2">{'Also delete these disks:'|t}</p>
{foreach $disks as $d}
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="disks[]" value="{$d.target}" id="disk-{$d.target}"{if $d.shared} disabled{else} checked{/if}>
        <label class="form-check-label" for="disk-{$d.target}">
          {$d.target}: <small>{$d.path}</small>
          {if $d.shared}<span class="badge text-bg-warning">{'used by another machine'|t}</span>{/if}
        </label>
      </div>
{foreachelse}
      <p class="text-body-secondary">{'No disks'|t}</p>
{/foreach}
      <p class="form-text">{'CD/DVD images are never deleted (except the cloud-init ISO of this machine).'|t}</p>
      <button type="submit" class="btn btn-danger">{'Delete machine'|t}</button>
    </form>
{/if}
  </div>
</div>
{/if}
{include file="footer.tpl"}
