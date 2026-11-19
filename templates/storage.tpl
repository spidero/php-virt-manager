{include file="header.tpl"}
{if $storage==""}
<div class="card">
  <div class="card-header">{'Storage pools'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'Name'|t}</th><th>{'State'|t}</th><th>{'Capacity'|t}</th><th>{'Allocated'|t}</th><th>{'Available'|t}</th><th>{'Autostart'|t}</th><th></th></tr></thead>
    <tbody>
{foreach from=$pools item=p}
    <tr>
      <td><a href="storage.php?storage={$p.name|escape:'url'}">{$p.name}</a></td>
      <td><span class="badge text-bg-{if $p.active}success{else}secondary{/if}">{$p.state|t}</span></td>
      <td>{$p.capacity}</td><td>{$p.allocation}</td><td>{$p.available}</td>
      <td>{if $p.autostart}{'on'|t}{else}{'off'|t}{/if}</td>
      <td class="text-end text-nowrap">
{if $allow.admin}
        {if $p.active}
          {call post_button url='storage.php' action='pool_stop' label={'Stop'|t} extra=['storage'=>$p.name] confirm={'Stop storage pool %s? Machines using it may fail.'|t:$p.name}}
        {else}
          {call post_button url='storage.php' action='pool_start' label={'Start'|t} class='btn-outline-success btn-sm' extra=['storage'=>$p.name]}
        {/if}
        {call post_button url='storage.php' action='pool_autostart' label=($p.autostart)?{'Disable autostart'|t}:{'Enable autostart'|t} extra=['storage'=>$p.name, 'enable'=>($p.autostart)?'0':'1']}
        {call post_button url='storage.php' action='pool_delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['storage'=>$p.name] confirm={'Remove storage pool %s from libvirt? Its files stay on the disk.'|t:$p.name}}
{/if}
      </td>
    </tr>
{foreachelse}
    <tr><td colspan="7">{'No storage pools found'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{if $allow.admin}
<div class="card mt-3">
  <div class="card-header">{'New storage pool'|t}</div>
  <div class="card-body">
    <form method="post" action="storage.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="pool_create">
      <div class="col-md-3">
        <label class="form-label" for="pool-name">{'Name'|t}</label>
        <input type="text" class="form-control form-control-sm" id="pool-name" name="name" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}" placeholder="vm-data">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-type">{'Type'|t}</label>
        <select class="form-select form-select-sm" id="pool-type" name="type">
{foreach $pool_types as $type => $label}
          <option value="{$type}">{$label|t}</option>
{/foreach}
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="pool-path">{'Target directory (directory, NFS mount point)'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-path" name="path" placeholder="/srv/vm-images">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-host">{'Server (NFS, iSCSI, Ceph monitor)'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-host" name="host" placeholder="nas.local">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-export">{'NFS export'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-export" name="export" placeholder="/export/vm">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-vg">{'LVM group / ZFS pool / Ceph pool'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-vg" name="vg" placeholder="vg_vms">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="pool-iqn">{'iSCSI target (IQN)'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-iqn" name="iqn" placeholder="iqn.2004-04.com.example:storage">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="pool-device">{'Disk device'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-device" name="device" placeholder="/dev/sdb">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-auth">{'Ceph user (optional)'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-auth" name="auth_user" placeholder="libvirt">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="pool-secret">{'Ceph secret UUID'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="pool-secret" name="secret" placeholder="uuid of a libvirt secret">
      </div>
      <div class="col-md-3">
        <div class="form-check mb-1"><input type="checkbox" class="form-check-input" id="pool-auto" name="autostart" value="1" checked> <label class="form-check-label" for="pool-auto">{'autostart'|t}</label></div>
        <button type="submit" class="btn btn-primary btn-sm w-100">{'Create'|t}</button>
      </div>
      <div class="col-12 form-text">{'Fill in the fields of the chosen type. The LVM volume group, ZFS pool and Ceph pool must already exist. Removing a pool later keeps its files.'|t}
        <span class="text-danger">{'Whole disk: the disk must already have a partition table - the panel never creates one, libvirt adds partitions as volumes.'|t}</span>
        {'Only the types this hypervisor supports are offered.'|t}</div>
    </form>
  </div>
</div>
{/if}
{else}
<div class="card mb-3">
  <div class="card-header">
    {'Storage'|t} <b>{$storage}</b>
    <span class="badge text-bg-{if $pool_active}success{else}secondary{/if}">{if $pool_active}{'active'|t}{else}{'inactive'|t}{/if}</span>
    <a href="storage.php" class="float-end small">&laquo; {'all pools'|t}</a>
  </div>
  <div class="card-body pb-2">
{if $allow.admin}
    {if $pool_active}
      {call post_button url='storage.php' action='pool_refresh' label={'Refresh'|t} extra=['storage'=>$storage]}
      {call post_button url='storage.php' action='pool_stop' label={'Stop'|t} extra=['storage'=>$storage] confirm={'Stop storage pool %s? Machines using it may fail.'|t:$storage}}
    {else}
      {call post_button url='storage.php' action='pool_start' label={'Start'|t} class='btn-outline-success btn-sm' extra=['storage'=>$storage]}
    {/if}
    {call post_button url='storage.php' action='pool_autostart' label=($pool_autostart)?{'Disable autostart'|t}:{'Enable autostart'|t} extra=['storage'=>$storage, 'enable'=>($pool_autostart)?'0':'1']}
    {call post_button url='storage.php' action='pool_delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['storage'=>$storage] confirm={'Remove storage pool %s from libvirt? Its files stay on the disk.'|t:$storage}}
{/if}
  </div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'Volume'|t}</th><th>{'Path'|t}</th><th>{'Capacity'|t}</th><th>{'Allocated'|t}</th><th></th></tr></thead>
    <tbody>
{foreach from=$volumes item=v}
    <tr>
      <td>{$v.name}</td><td><small>{$v.path}</small></td><td>{$v.capacity}</td><td>{$v.allocation}</td>
      <td class="text-end text-nowrap">
{if $allow.admin}
        <form method="post" action="storage.php" class="d-inline-flex gap-1 me-1 mb-1">
          <input type="hidden" name="csrf" value="{$csrf_token}">
          <input type="hidden" name="action" value="volume_resize">
          <input type="hidden" name="storage" value="{$storage}">
          <input type="hidden" name="volume" value="{$v.name}">
          <input type="number" name="size" class="form-control form-control-sm" style="width: 5.5rem" min="{$v.capacity_gb+1}" max="65536" value="{$v.capacity_gb+10}" aria-label="{'New size (GB)'|t}" required>
          <button type="submit" class="btn btn-outline-primary btn-sm">{'GB, resize'|t}</button>
        </form>
        <form method="post" action="storage.php" class="d-inline-flex gap-1 me-1 mb-1">
          <input type="hidden" name="csrf" value="{$csrf_token}">
          <input type="hidden" name="action" value="volume_clone">
          <input type="hidden" name="storage" value="{$storage}">
          <input type="hidden" name="volume" value="{$v.name}">
          <input type="text" name="name" class="form-control form-control-sm" style="width: 9rem" value="copy-{$v.name}" aria-label="{'Name of the copy'|t}" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,127{rdelim}">
          <button type="submit" class="btn btn-outline-secondary btn-sm">{'Copy'|t}</button>
        </form>
{/if}
{if $v.in_use}
        <span class="badge text-bg-info">{'in use'|t}</span>
{elseif $allow.admin}
        {call post_button url='storage.php' action='volume_delete' label={'Delete'|t} class='btn-outline-danger btn-sm' extra=['storage'=>$storage, 'volume'=>$v.name] confirm={'Delete volume %s? This cannot be undone.'|t:$v.name}}
{/if}
      </td>
    </tr>
{foreachelse}
    <tr><td colspan="5">{'No volumes (or pool inactive)'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
{if $allow.admin && $pool_active}
  <div class="card-body">
    <form method="post" action="storage.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="volume_create">
      <input type="hidden" name="storage" value="{$storage}">
      <div class="col-md-5">
        <label class="form-label" for="vol_name">{'New volume'|t}</label>
        <input type="text" class="form-control form-control-sm" id="vol_name" name="name" placeholder="data.qcow2" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,127{rdelim}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="vol_size">{'Size (GB)'|t}</label>
        <input type="number" class="form-control form-control-sm" id="vol_size" name="size" value="10" min="1" max="4096" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="vol_format">{'Format'|t}</label>
        <select class="form-select form-select-sm" id="vol_format" name="format"><option>qcow2</option><option>raw</option></select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary btn-sm w-100">{'Create volume'|t}</button>
      </div>
    </form>
    <form method="post" action="storage.php" class="row g-2 align-items-end mt-2">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="iso_download">
      <input type="hidden" name="storage" value="{$storage}">
      <div class="col-md-6">
        <label class="form-label" for="iso_url">{'Download ISO from URL'|t}</label>
        <input type="url" class="form-control form-control-sm font-monospace" id="iso_url" name="url" placeholder="https://.../debian-13-amd64-netinst.iso" required>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="iso_name">{'Volume name (optional)'|t}</label>
        <input type="text" class="form-control form-control-sm" id="iso_name" name="name" placeholder="netinst.iso">
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">{'Download'|t}</button>
      </div>
    </form>
  </div>
{/if}
</div>
<div class="card">
  <div class="card-header">{'XML definition'|t}</div>
  <pre class="bg-body-tertiary p-3 mb-0"><code>{$xml}</code></pre>
</div>
{/if}
{include file="footer.tpl"}
