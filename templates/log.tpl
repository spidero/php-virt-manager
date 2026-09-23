{include file="header.tpl"}
<div class="card">
  <div class="card-header">Action log <small class="text-muted">(last 500 entries)</small></div>
  <div class="table-responsive">
  <table class="table table-sm table-striped mb-0">
    <thead><tr><th>Time</th><th>User</th><th>IP</th><th>Action</th><th>Target</th><th>Result</th><th>Details</th></tr></thead>
    <tbody>
{foreach from=$entries item=e}
      <tr>
        <td class="text-nowrap"><small>{$e.time|date_format:'%Y-%m-%d %H:%M:%S'}</small></td>
        <td>{$e.user}</td><td><small>{$e.ip}</small></td><td>{$e.action}</td><td>{$e.target}</td>
        <td>{if $e.ok}<span class="badge text-bg-success">ok</span>{else}<span class="badge text-bg-danger">failed</span>{/if}</td>
        <td><small>{$e.details}</small></td>
      </tr>
{foreachelse}
      <tr><td colspan="7">Log is empty</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{include file="footer.tpl"}
