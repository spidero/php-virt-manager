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
  </div>
{/if}
</div>
<div class="card">
  <div class="card-header">{'XML definition'|t}</div>
  <pre class="bg-body-tertiary p-3 mb-0"><code>{$xml}</code></pre>
</div>
{/if}
{include file="footer.tpl"}
