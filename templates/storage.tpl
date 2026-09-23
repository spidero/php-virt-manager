{include file="header.tpl"}
<div class="card">
  <div class="card-body">

{if $storage==""}
<h5>Storage pools</h5>
<table class="table table-sm">
  <thead><tr><th>Name</th><th>State</th><th>Capacity</th><th>Allocated</th><th>Available</th></tr></thead>
  <tbody>
{foreach from=$pools item=p}
    <tr>
      <td><a href="storage.php?storage={$p.name|escape:'url'}">{$p.name}</a></td>
      <td>{$p.state}</td><td>{$p.capacity}</td><td>{$p.allocation}</td><td>{$p.available}</td>
    </tr>
{foreachelse}
    <tr><td colspan="5">No storage pools found</td></tr>
{/foreach}
  </tbody>
</table>
{else}
<h5>Storage <b>{$storage}</b></h5>
<a href="storage.php">&laquo; all pools</a>
<hr>
<h6>Volumes</h6>
<table class="table table-sm">
  <thead><tr><th>Name</th><th>Path</th><th>Capacity</th><th>Allocated</th></tr></thead>
  <tbody>
{foreach from=$volumes item=v}
    <tr><td>{$v.name}</td><td><small>{$v.path}</small></td><td>{$v.capacity}</td><td>{$v.allocation}</td></tr>
{foreachelse}
    <tr><td colspan="4">No volumes (or pool inactive)</td></tr>
{/foreach}
  </tbody>
</table>
<h6>XML definition</h6>
<pre class="bg-body-tertiary p-2"><code>{$xml}</code></pre>
{/if}

  </div>
</div>
{include file="footer.tpl"}
