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
    <h5>{$node} <span class="badge text-bg-{$state.color}">{$state.label|t}</span></h5>
    <p class="text-body-secondary mb-3"><small>UUID: {$domain_uuid}</small></p>

{if $allow.operate}
    <div class="mb-3">
    {if $info['state']==1}
        {call action_button action='stop' label={'Shut down'|t}}
        {call action_button action='reboot' label={'Reboot'|t}}
        {call action_button action='suspend' label={'Pause'|t}}
        {call action_button action='destroy' label={'Force stop'|t} class='btn-danger' confirm={'Force stop %s? Unsaved data in the machine will be lost.'|t:$node}}
    {elseif $info['state']==3}
        {call action_button action='resume' label={'Resume'|t}}
        {call action_button action='destroy' label={'Force stop'|t} class='btn-danger' confirm={'Force stop %s? Unsaved data in the machine will be lost.'|t:$node}}
    {elseif $info['state']==5}
        {call action_button action='start' label={'Start'|t} class='btn-success'}
    {/if}
        <a href="edit.php?node={$node|escape:'url'}" class="btn btn-outline-secondary mb-1">{'Edit'|t}</a>
    {if $graphics && $graphics.type=='vnc' && $active && $console_enabled}
        <a href="console.php?node={$node|escape:'url'}" target="_blank" class="btn btn-secondary mb-1">{'Console'|t}</a>
    {/if}
    </div>
{/if}

    <div class="row">
      <div class="col-md-6">
        <table class="table table-sm">
          <tr><th>{'Memory'|t}</th><td>{'%s GB (max %s GB)'|t:$info['memory']:$info['maxMem']}</td></tr>
          <tr><th>{'vCPUs'|t}</th><td>{$info['nrVirtCpu']}</td></tr>
          <tr><th>{'CPU time'|t}</th><td>{$info['cpuUsed']} s</td></tr>
          <tr><th>{'Autostart'|t}</th><td>
            {if $autostart}{'on'|t}{else}{'off'|t}{/if}
            {if $allow.operate}
              {call action_button action='autostart' label=($autostart)?{'disable'|t}:{'enable'|t} class='btn-link btn-sm p-0 ms-2' extra=['enable'=>($autostart)?'0':'1']}
            {/if}
          </td></tr>
          <tr><th>{'Graphics'|t}</th><td>
{if $graphics}
            {$graphics.type|upper}{if $active && $graphics.port > 0} {$graphics.listen}:{$graphics.port}{/if}
{else}
            {'none'|t}
{/if}
{if $persistent_graphics && $persistent_graphics.type=='spice' && $allow.operate}
            {call action_button action='switch_vnc' label={'switch to VNC'|t} class='btn-link btn-sm p-0 ms-2' confirm={'Switch %s from SPICE to VNC? SPICE agent channels and USB redirection will be removed. Takes effect after the machine is shut down and started again.'|t:$node}}
{/if}
          </td></tr>
        </table>
      </div>
      <div class="col-md-6 text-center">
{if $active}
        <img id="screenshot" src="screenshot.php?node={$node|escape:'url'}" alt="{'screen of %s'|t:$node}" class="img-fluid border" style="max-height: 240px;">
        <div><small class="text-body-secondary">{'screen preview, refreshed every 5 s'|t}</small></div>
{/if}
      </div>
    </div>
  </div>
</div>

{if $active}
<div class="card mb-3">
  <div class="card-header">{'Live usage'|t} <small class="text-body-secondary">({'last 5 minutes, every 5 s'|t})</small></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="cpu" data-labels="{'CPU'|t}" data-unit="%"></canvas></div>
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="mem" data-labels="{'Memory (host RSS)'|t}" data-unit="MB"></canvas></div>
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="disk_rd,disk_wr" data-labels="{'Disk read'|t},{'Disk write'|t}" data-unit="MB/s"></canvas></div>
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="net_rx,net_tx" data-labels="{'Network in'|t},{'Network out'|t}" data-unit="MB/s"></canvas></div>
    </div>
  </div>
</div>
{/if}

<div class="card mb-3">
  <div class="card-header">{'Disks'|t}</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>{'Target'|t}</th><th>{'Type'|t}</th><th>{'Bus'|t}</th><th>{'Source'|t}</th><th>{'Size'|t}</th></tr></thead>
    <tbody>
{foreach from=$disks item=d}
      <tr><td>{$d.target}</td><td>{$d.device}</td><td>{$d.bus}</td><td><small>{$d.source|default:'-'}</small></td><td>{$d.size}</td></tr>
{foreachelse}
      <tr><td colspan="5">{'No disks'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
</div>

<div class="card mb-3">
  <div class="card-header">{'Network interfaces'|t}</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>{'Device'|t}</th><th>{'Source'|t}</th><th>{'Model'|t}</th><th>MAC</th><th>IP</th></tr></thead>
    <tbody>
{foreach from=$interfaces item=i}
      <tr>
        <td>{$i.target|default:'-'}</td><td>{$i.type}: {$i.source}</td><td>{$i.model}</td><td><code>{$i.mac}</code></td>
        <td>{foreach $i.ips as $ip}{$ip}<br>{foreachelse}<span class="text-body-secondary">-</span>{/foreach}</td>
      </tr>
{foreachelse}
      <tr><td colspan="5">{'No network interfaces'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
</div>

<div class="card mb-3">
  <div class="card-header">{'Snapshots'|t}</div>
  <table class="table table-sm mb-0">
    <thead><tr><th>{'Name'|t}</th><th>{'Created'|t}</th><th>{'VM state'|t}</th><th>{'Description'|t}</th><th></th></tr></thead>
    <tbody>
{foreach from=$snapshots item=s}
      <tr>
        <td>{$s.name}</td><td>{$s.created|date_format:'%Y-%m-%d %H:%M'}</td><td>{$s.state|t}</td><td>{$s.description}</td>
        <td class="text-end text-nowrap">
{if $allow.operate}
          {call action_button action='snapshot_revert' label={'Revert'|t} class='btn-outline-warning btn-sm' extra=['snapshot'=>$s.name] confirm={'Revert %s to snapshot %s? Current state will be lost.'|t:$node:$s.name}}
          {call action_button action='snapshot_delete' label={'Delete'|t} class='btn-outline-danger btn-sm' extra=['snapshot'=>$s.name] confirm={'Delete snapshot %s?'|t:$s.name}}
{/if}
        </td>
      </tr>
{foreachelse}
      <tr><td colspan="5">{'No snapshots'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
{if $schedules || $allow.admin}
  <div class="card-body border-bottom">
    <h6>{'Automatic snapshots'|t}</h6>
{foreach $schedules as $s}
    <div class="d-flex flex-wrap align-items-center mb-1">
      <span class="me-2">{include file="schedule_text.tpl"}</span>
      {if !$s.enabled}<span class="badge text-bg-secondary me-2">{'paused'|t}</span>{/if}
      <small class="text-body-secondary me-2">{if $s.last_run}{'last: %s'|t:($s.last_run|date_format:'%Y-%m-%d %H:%M')} - {$s.last_status}{/if}</small>
{if $allow.admin}
      {call action_button action='schedule_toggle' label=($s.enabled)?{'Pause'|t}:{'Resume'|t} class='btn-outline-secondary btn-sm' extra=['schedule'=>$s.id]}
      {call action_button action='schedule_delete' label={'Delete'|t} class='btn-outline-danger btn-sm' extra=['schedule'=>$s.id] confirm={'Delete this schedule? Its snapshots are kept.'|t}}
{/if}
    </div>
{/foreach}
{if $allow.admin}
    <form method="post" action="node.php" class="d-flex flex-wrap align-items-center gap-1 mt-2">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="node" value="{$node}">
      <input type="hidden" name="action" value="schedule_add">
      <select name="frequency" class="form-select form-select-sm w-auto" aria-label="{'Frequency'|t}">
        <option value="daily">{'daily'|t}</option><option value="hourly">{'hourly'|t}</option><option value="weekly">{'weekly'|t}</option>
      </select>
      <select name="weekday" class="form-select form-select-sm w-auto" aria-label="{'Weekday'|t}">
{foreach $weekdays as $i => $day}
        <option value="{$i}"{if $i==0} selected{/if}>{$day|t}</option>
{/foreach}
      </select>
      <select name="hour" class="form-select form-select-sm w-auto" aria-label="{'Hour'|t}">
{for $h=0 to 23}
        <option value="{$h}"{if $h==2} selected{/if}>{$h|string_format:'%02d'}:00</option>
{/for}
      </select>
      <label class="small ms-1" for="keep">{'keep'|t}</label>
      <input type="number" id="keep" name="keep" value="7" min="1" max="100" class="form-control form-control-sm" style="width: 5rem">
      <button type="submit" class="btn btn-outline-primary btn-sm">{'Add schedule'|t}</button>
    </form>
    <div class="form-text">{'Hour and weekday are ignored where they do not apply. Only the automatic snapshots of a schedule are removed by its retention.'|t}</div>
{/if}
  </div>
{/if}
{if $allow.operate}
  <div class="card-body">
    <form method="post" action="node.php" class="d-flex flex-wrap align-items-center">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="node" value="{$node}">
      <input type="hidden" name="action" value="snapshot_create">
      <input type="text" name="name" class="form-control form-control-sm w-auto me-2 mb-1" placeholder="{'name (default: date)'|t}" pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
      <input type="text" name="description" class="form-control form-control-sm w-auto me-2 mb-1" placeholder="{'description'|t}">
      <button type="submit" class="btn btn-primary btn-sm mb-1">{'Create snapshot'|t}</button>
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
