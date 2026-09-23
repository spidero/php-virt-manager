{include file="header.tpl"}
<div class="card">
  <div class="card-header">{'Action log'|t} <small class="text-body-secondary">({'last 500 entries'|t})</small></div>
  <div class="table-responsive">
  <table class="table table-sm table-striped mb-0">
    <thead><tr><th>{'Time'|t}</th><th>{'User'|t}</th><th>IP</th><th>{'Action'|t}</th><th>{'Target'|t}</th><th>{'Result'|t}</th><th>{'Details'|t}</th></tr></thead>
    <tbody>
{foreach from=$entries item=e}
      <tr>
        <td class="text-nowrap"><small>{$e.time|date_format:'%Y-%m-%d %H:%M:%S'}</small></td>
        <td>{$e.user}</td><td><small>{$e.ip}</small></td><td>{$e.action}</td><td class="text-nowrap">{$e.target}</td>
        <td>{if $e.ok}<span class="badge text-bg-success">{'ok'|t}</span>{else}<span class="badge text-bg-danger">{'failed'|t}</span>{/if}</td>
        <td class="text-break"><small>{$e.details}</small></td>
      </tr>
{foreachelse}
      <tr><td colspan="7">{'Log is empty'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{include file="footer.tpl"}
