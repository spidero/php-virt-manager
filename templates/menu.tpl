<div class="card mb-3">
  <div class="card-header">
    <a href="index.php">{$connection}</a>
  </div>
  <div class="card-body px-3 py-2">
{foreach from=$domains item=d}
    <div class="text-truncate">
      <span class="text-{$d.color}" title="{$d.label}">&#9679;</span>
      <a href="node.php?node={$d.name|escape:'url'}"{if isset($node) && $node==$d.name} class="font-weight-bold"{/if}>{$d.name}</a>
    </div>
{foreachelse}
    No machines found
{/foreach}
{if !$readonly}
    <a href="create.php" class="btn btn-outline-primary btn-sm btn-block mt-2">+ New machine</a>
{/if}
  </div>
  <div class="list-group list-group-flush">
    <a href="index.php" class="list-group-item list-group-item-action py-2{if $page=='index'} active{/if}">Hypervisor</a>
    <a href="storage.php" class="list-group-item list-group-item-action py-2{if $page=='storage'} active{/if}">Storage</a>
    <a href="network.php" class="list-group-item list-group-item-action py-2{if $page=='network'} active{/if}">Networks</a>
    <a href="log.php" class="list-group-item list-group-item-action py-2{if $page=='log'} active{/if}">Action log</a>
  </div>
  <div class="card-footer">
    <form method="post" action="logout.php" class="m-0">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <small>{$logged_user}</small>
      <button type="submit" class="btn btn-link btn-sm p-0 float-right">Logout</button>
    </form>
  </div>
</div>
