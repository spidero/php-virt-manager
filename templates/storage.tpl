{include file="header.tpl"}
<div class="card">
  <div class="card-body">

{if $storage==""}
<h5>{'Storage pools'|t}</h5>
<table class="table table-sm">
  <thead><tr><th>{'Name'|t}</th><th>{'State'|t}</th><th>{'Capacity'|t}</th><th>{'Allocated'|t}</th><th>{'Available'|t}</th></tr></thead>
  <tbody>
{foreach from=$pools item=p}
    <tr>
      <td><a href="storage.php?storage={$p.name|escape:'url'}">{$p.name}</a></td>
      <td>{$p.state|t}</td><td>{$p.capacity}</td><td>{$p.allocation}</td><td>{$p.available}</td>
    </tr>
{foreachelse}
    <tr><td colspan="5">{'No storage pools found'|t}</td></tr>
{/foreach}
  </tbody>
</table>
{else}
<h5>{'Storage'|t} <b>{$storage}</b></h5>
<a href="storage.php">&laquo; {'all pools'|t}</a>
<hr>
<h6>{'Volumes'|t}</h6>
<table class="table table-sm">
  <thead><tr><th>{'Name'|t}</th><th>{'Path'|t}</th><th>{'Capacity'|t}</th><th>{'Allocated'|t}</th></tr></thead>
  <tbody>
{foreach from=$volumes item=v}
    <tr><td>{$v.name}</td><td><small>{$v.path}</small></td><td>{$v.capacity}</td><td>{$v.allocation}</td></tr>
{foreachelse}
    <tr><td colspan="4">{'No volumes (or pool inactive)'|t}</td></tr>
{/foreach}
  </tbody>
</table>
<h6>{'XML definition'|t}</h6>
<pre class="bg-body-tertiary p-2"><code>{$xml}</code></pre>
{/if}

  </div>
</div>
{include file="footer.tpl"}
