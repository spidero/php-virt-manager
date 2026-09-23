{include file="header.tpl"}
{* form posting an action to node.php; $confirm adds a JS confirmation *}
{function name=action_button action='' label='' class='btn-info' confirm='' extra=[]}
<form method="post" action="node.php" class="d-inline-block me-1 mb-1"{if $confirm} data-confirm="{$confirm}"{/if}>
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="node" value="{$node}">
  <input type="hidden" name="action" value="{$action}">
{foreach $extra as $k => $v}
  <input type="hidden" name="{$k}" value="{$v}">
{/foreach}
  <button type="submit" class="btn {$class}">{$label}</button>
</form>
{/function}
<div class="card mb-3">
  <div class="card-body">
    <h5>{$node} <span class="badge text-bg-{$state.color}">{$state.label}</span></h5>
    <p class="text-muted mb-3"><small>UUID: {$domain_uuid}</small></p>

{if !$readonly}
    <div class="mb-3">
    {if $info['state']==1}
        {call action_button action='stop' label='Shut down'}
        {call action_button action='reboot' label='Reboot'}
        {call action_button action='suspend' label='Pause'}
        {call action_button action='destroy' label='Force stop' class='btn-danger' confirm="Force stop `$node`? Unsaved data in the machine will be lost."}
    {elseif $info['state']==3}
        {call action_button action='resume' label='Resume'}
        {call action_button action='destroy' label='Force stop' class='btn-danger' confirm="Force stop `$node`? Unsaved data in the machine will be lost."}
    {elseif $info['state']==5}
        {call action_button action='start' label='Start' class='btn-success'}
    {/if}
    {if $graphics && $graphics.type=='vnc' && $active && $console_enabled}
        <a href="console.php?node={$node|escape:'url'}" target="_blank" class="btn btn-dark">Console</a>
    {/if}
    </div>
{/if}

    <div class="row">
      <div class="col-md-6">
        <table class="table table-sm">
          <tr><th>Memory</th><td>{$info['memory']} GB (max {$info['maxMem']} GB)</td></tr>
          <tr><th>vCPUs</th><td>{$info['nrVirtCpu']}</td></tr>
          <tr><th>CPU time</th><td>{$info['cpuUsed']} s</td></tr>
          <tr><th>Autostart</th><td>
            {if $autostart}on{else}off{/if}
            {if !$readonly}
              {call action_button action='autostart' label=($autostart)?'disable':'enable' class='btn-link btn-sm p-0 ms-2' extra=['enable'=>($autostart)?'0':'1']}
            {/if}
          </td></tr>
          <tr><th>Graphics</th><td>
{if $graphics}
            {$graphics.type|upper}{if $active && $graphics.port > 0} {$graphics.listen}:{$graphics.port}{/if}
{else}
            none
{/if}
{if $persistent_graphics && $persistent_graphics.type=='spice' && !$readonly}
            {call action_button action='switch_vnc' label='switch to VNC' class='btn-link btn-sm p-0 ms-2' confirm="Switch `$node` from SPICE to VNC? SPICE agent channels and USB redirection will be removed. Takes effect after the machine is shut down and started again."}
{/if}
          </td></tr>
        </table>
      </div>
      <div class="col-md-6 text-center">
{if $active}
        <img id="screenshot" src="screenshot.php?node={$node|escape:'url'}" alt="screen of {$node}" class="img-fluid border" style="max-height: 240px;">
        <div><small class="text-muted">screen preview, refreshed every 5 s</small></div>
{/if}
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">Disks</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>Target</th><th>Type</th><th>Bus</th><th>Source</th><th>Size</th></tr></thead>
    <tbody>
{foreach from=$disks item=d}
      <tr><td>{$d.target}</td><td>{$d.device}</td><td>{$d.bus}</td><td><small>{$d.source|default:'-'}</small></td><td>{$d.size}</td></tr>
{foreachelse}
      <tr><td colspan="5">No disks</td></tr>
{/foreach}
    </tbody>
  </table>
</div>

<div class="card mb-3">
  <div class="card-header">Network interfaces</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>Device</th><th>Source</th><th>Model</th><th>MAC</th><th>IP</th></tr></thead>
    <tbody>
{foreach from=$interfaces item=i}
      <tr>
        <td>{$i.target|default:'-'}</td><td>{$i.type}: {$i.source}</td><td>{$i.model}</td><td><code>{$i.mac}</code></td>
        <td>{foreach $i.ips as $ip}{$ip}<br>{foreachelse}<span class="text-muted">-</span>{/foreach}</td>
      </tr>
{foreachelse}
      <tr><td colspan="5">No network interfaces</td></tr>
{/foreach}
    </tbody>
  </table>
</div>

<div class="card mb-3">
  <div class="card-header">Snapshots</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>Name</th><th>Created</th><th>VM state</th><th>Description</th><th></th></tr></thead>
    <tbody>
{foreach from=$snapshots item=s}
      <tr>
        <td>{$s.name}</td><td>{$s.created|date_format:'%Y-%m-%d %H:%M'}</td><td>{$s.state}</td><td>{$s.description}</td>
        <td class="text-end text-nowrap">
{if !$readonly}
          {call action_button action='snapshot_revert' label='Revert' class='btn-outline-warning btn-sm' extra=['snapshot'=>$s.name] confirm="Revert `$node` to snapshot `$s.name`? Current state will be lost."}
          {call action_button action='snapshot_delete' label='Delete' class='btn-outline-danger btn-sm' extra=['snapshot'=>$s.name] confirm="Delete snapshot `$s.name`?"}
{/if}
        </td>
      </tr>
{foreachelse}
      <tr><td colspan="5">No snapshots</td></tr>
{/foreach}
    </tbody>
  </table>
{if !$readonly}
  <div class="card-body">
    <form method="post" action="node.php" class="d-flex flex-wrap align-items-center">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="node" value="{$node}">
      <input type="hidden" name="action" value="snapshot_create">
      <input type="text" name="name" class="form-control form-control-sm w-auto me-2 mb-1" placeholder="name (default: date)" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
      <input type="text" name="description" class="form-control form-control-sm w-auto me-2 mb-1" placeholder="description">
      <button type="submit" class="btn btn-primary btn-sm mb-1">Create snapshot</button>
    </form>
  </div>
{/if}
</div>

{if $active}
<script>
  // refresh screen preview without caching
  setInterval(function () {
    var img = document.getElementById('screenshot');
    if (img) img.src = img.src.replace(/&t=\d+$/, '') + '&t=' + Date.now();
  }, 5000);
</script>
{/if}
{include file="footer.tpl"}
