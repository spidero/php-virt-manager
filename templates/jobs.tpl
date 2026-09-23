{include file="header.tpl"}
<div class="card">
  <div class="card-header">{'Background tasks'|t}
{if $active_jobs}
    <small class="text-body-secondary">({'refreshing every 5 s'|t})</small>
{/if}
  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr><th>#</th><th>{'Task'|t}</th><th>{'Target'|t}</th><th>{'User'|t}</th><th>{'Created'|t}</th><th>{'State'|t}</th><th>{'Details'|t}</th></tr></thead>
    <tbody>
{foreach $jobs as $j}
      <tr>
        <td>{$j.id}</td>
        <td>{$j.type|t}</td>
        <td class="text-nowrap">{$j.params.name|default:'-'}</td>
        <td>{$j.username}</td>
        <td class="text-nowrap"><small>{$j.created_at|date_format:'%Y-%m-%d %H:%M:%S'}</small></td>
        <td>
          {if $j.status=='done'}<span class="badge text-bg-success">{'done'|t}</span>
          {elseif $j.status=='failed'}<span class="badge text-bg-danger">{'failed'|t}</span>
          {elseif $j.status=='running'}<span class="badge text-bg-primary">{'running'|t}</span>
          {else}<span class="badge text-bg-secondary">{'queued'|t}</span>{/if}
        </td>
        <td><small>{$j.message}</small></td>
      </tr>
{foreachelse}
      <tr><td colspan="7">{'No tasks'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{if $active_jobs}
<script>setTimeout(function () { location.reload(); }, 5000);</script>
{/if}
{include file="footer.tpl"}
