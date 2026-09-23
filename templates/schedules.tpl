{include file="header.tpl"}
<div class="card">
  <div class="card-header">{'Snapshot schedules'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr><th>{'Connection'|t}</th><th>{'Machine'|t}</th><th>{'Schedule'|t}</th><th>{'State'|t}</th><th>{'Last run'|t}</th><th>{'Result'|t}</th></tr></thead>
    <tbody>
{foreach $schedules as $s}
      <tr>
        <td>{$connections[$s.conn].label|default:$s.conn}</td>
        <td>{if $s.conn==$connection_key}<a href="node.php?node={$s.domain|escape:'url'}">{$s.domain}</a>{else}{$s.domain}{/if}</td>
        <td>{include file="schedule_text.tpl"}</td>
        <td>{if $s.enabled}<span class="badge text-bg-success">{'active'|t}</span>{else}<span class="badge text-bg-secondary">{'paused'|t}</span>{/if}</td>
        <td class="text-nowrap"><small>{if $s.last_run}{$s.last_run|date_format:'%Y-%m-%d %H:%M'}{else}-{/if}</small></td>
        <td><small>{$s.last_status|default:'-'}</small></td>
      </tr>
{foreachelse}
      <tr><td colspan="6">{'No schedules. Add them on the machine page, in the Snapshots section.'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{include file="footer.tpl"}
