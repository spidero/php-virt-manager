{include file="header.tpl"}
{function name=form_start action=''}
<form method="post" action="edit.php">
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="node" value="{$node}">
  <input type="hidden" name="action" value="{$action}">
{/function}
<h5 class="mb-3">{'Edit %s'|t:$node} <a href="node.php?node={$node|escape:'url'}" class="btn btn-link btn-sm">&laquo; {'back'|t}</a></h5>
{if $active}
<div class="alert alert-info">{'The machine is running: memory, vCPU and boot order changes take effect after it is shut down and started again. Disks and network interfaces are attached immediately.'|t}</div>
{/if}

<div class="row">
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header">{'Memory and vCPUs'|t}</div>
      <div class="card-body">
        {call form_start action='resources'}
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label" for="memory">{'Memory (MB)'|t}</label>
              <input type="number" class="form-control" id="memory" name="memory" value="{$memory_mb}" min="128" max="{$max_memory_mb}" step="128" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label" for="vcpus">{'vCPUs'|t}</label>
              <input type="number" class="form-control" id="vcpus" name="vcpus" value="{$vcpus}" min="1" max="{$max_vcpus}" required>
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
{foreach $networks as $n}
              <option value="{$n}">{$n}</option>
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
