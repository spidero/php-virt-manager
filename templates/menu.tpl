<div class="card">
  <div class="card-header">
    <a href="index.php">{$connection}</a>
  </div>
  <div class="card-body">
{foreach from=$domains item=d}
<a href="node.php?node={$d}">{$d}</a><br>
{foreachelse}
    No items were found in the search
{/foreach}
  </div>
  <div class="card-footer">
    
  </div>
</div>
