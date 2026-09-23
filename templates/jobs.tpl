{include file="header.tpl"}
<div class="card">
  <div class="card-header">{'Background tasks'|t}

  </div>
  <div class="table-responsive">
  <table class="table table-sm mb-0">
    <thead><tr><th>#</th><th>{'Task'|t}</th><th>{'Target'|t}</th><th>{'User'|t}</th><th>{'Created'|t}</th><th>{'State'|t}</th><th>{'Details'|t}</th></tr></thead>
    <tbody>
{foreach $jobs as $j}
      <tr>
        <td>{$j.id}</td>
        <td>{$j.label|t}</td>
        <td class="text-nowrap">{$j.params.name|default:'-'}</td>
        <td>{$j.username}</td>
        <td class="text-nowrap"><small>{$j.created_at|date_format:'%Y-%m-%d %H:%M:%S'}</small></td>
{if $j.status=='done' || $j.status=='failed'}
        <td>
          {if $j.status=='done'}<span class="badge text-bg-success">{'done'|t}</span>{else}<span class="badge text-bg-danger">{'failed'|t}</span>{/if}
        </td>
        <td class="text-break"><small>{$j.message}</small></td>
{else}
        <td colspan="2">{include file="job_progress.tpl" job=$j}</td>
{/if}
      </tr>
{foreachelse}
      <tr><td colspan="7">{'No tasks'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{include file="footer.tpl"}
