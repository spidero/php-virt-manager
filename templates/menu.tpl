<div class="card">
  <div class="card-header">
    <a href="index.php">{$connection}</a>
  </div>
  <div class="card-body">
{foreach from=$domains item=d}
<a href="node.php?node={$d|escape:'url'}">{$d}</a><br>
{foreachelse}
    No machines found
{/foreach}
    <hr>
    <a href="storage.php">Storage</a>
  </div>
  <div class="card-footer">
    <form method="post" action="logout.php" class="m-0">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <small>{$logged_user}</small>
      <button type="submit" class="btn btn-link btn-sm p-0 float-right">Logout</button>
    </form>
  </div>
</div>
