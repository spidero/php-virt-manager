<div class="card mb-3">
  <div class="card-header">
{if count($connections) > 1}
    <form method="get" action="index.php" class="m-0">
      <select name="conn" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{'Hypervisor'|t}">
{foreach $connections as $key => $c}
        <option value="{$key}"{if $key==$connection_key} selected{/if}>{$c.label}</option>
{/foreach}
      </select>
    </form>
{else}
    <a href="index.php">{$connection}</a>
{/if}
    {if $readonly}<span class="badge text-bg-secondary mt-1">{'read-only'|t}</span>{/if}
  </div>
  <div class="card-body px-3 py-2">
{foreach from=$domains item=d}
    <div class="text-truncate">
      <span class="text-{$d.color}" title="{$d.label|t}" data-domain-dot="{$d.name}">&#9679;</span>
      <a href="node.php?node={$d.name|escape:'url'}"{if isset($node) && $node==$d.name} class="fw-bold"{/if}>{$d.name}</a>
    </div>
{foreachelse}
    {'No machines found'|t}
{/foreach}
{if $allow.operate}
    <div class="d-flex gap-1 mt-2">
      <a href="create.php" class="btn btn-outline-primary btn-sm flex-fill">+ {'From ISO'|t}</a>
      <a href="cloud.php" class="btn btn-outline-primary btn-sm flex-fill">+ {'From cloud image'|t}</a>
    </div>
{/if}
  </div>
  <div class="list-group list-group-flush">
    <a href="index.php" class="list-group-item list-group-item-action py-2{if $page=='index'} active{/if}">{'Hypervisor'|t}</a>
    <a href="storage.php" class="list-group-item list-group-item-action py-2{if $page=='storage'} active{/if}">{'Storage'|t}</a>
    <a href="network.php" class="list-group-item list-group-item-action py-2{if $page=='network'} active{/if}">{'Networks'|t}</a>
{if $can.operate}
    <a href="jobs.php" class="list-group-item list-group-item-action py-2{if $page=='jobs'} active{/if}">{'Background tasks'|t}</a>
{/if}
{if $can.admin}
    <a href="log.php" class="list-group-item list-group-item-action py-2{if $page=='log'} active{/if}">{'Action log'|t}</a>
    <a href="schedules.php" class="list-group-item list-group-item-action py-2{if $page=='schedules'} active{/if}">{'Snapshot schedules'|t}</a>
    <a href="users.php" class="list-group-item list-group-item-action py-2{if $page=='users'} active{/if}">{'Users'|t}</a>
{/if}
  </div>
  <div class="card-footer">
    <form method="post" action="logout.php" class="m-0">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <a href="profile.php" class="small">{$logged_user}</a> <small class="text-body-secondary">({$user_role|t})</small>
      <button type="submit" class="btn btn-link btn-sm p-0 float-end">{'Logout'|t}</button>
    </form>
  </div>
</div>
