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
    <a href="cloud.php" class="btn btn-outline-primary btn-sm w-100 mt-2">+ {'From cloud image'|t}</a>
    <a href="create.php" class="btn btn-outline-secondary btn-sm w-100 mt-1">+ {'From ISO'|t}</a>
{/if}
  </div>
  <div class="list-group list-group-flush">
    <a href="index.php" class="list-group-item list-group-item-action py-2{if $page=='index'} active{/if}">{'Hypervisor'|t}</a>
    <a href="storage.php" class="list-group-item list-group-item-action py-2{if $page=='storage'} active{/if}">{'Storage'|t}</a>
    <a href="network.php" class="list-group-item list-group-item-action py-2{if $page=='network'} active{/if}">{'Networks'|t}</a>
{if $can.operate}
    <a href="jobs.php" class="list-group-item list-group-item-action py-2{if $page=='jobs'} active{/if}">{'Background tasks'|t}</a>
    <a href="sshkeys.php" class="list-group-item list-group-item-action py-2{if $page=='sshkeys'} active{/if}">{'SSH keys'|t}</a>
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
      {* theme toggle: automatic -> light -> dark, handled by panel.js *}
      <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline text-body-secondary" data-theme-toggle
              data-label-auto="{'Theme: automatic (system)'|t}" data-label-light="{'Theme: light'|t}" data-label-dark="{'Theme: dark'|t}">
        <svg data-theme-icon="auto" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 15A7 7 0 1 0 8 1zm0 1A8 8 0 1 1 8 0a8 8 0 0 1 0 16"/></svg>
        <svg data-theme-icon="light" class="d-none" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0m0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13m8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5M3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8m10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0m-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0m9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707M4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708"/></svg>
        <svg data-theme-icon="dark" class="d-none" width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 .278a.77.77 0 0 1 .08.858 7.2 7.2 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277q.792-.001 1.533-.16a.79.79 0 0 1 .81.316.73.73 0 0 1-.031.893A8.35 8.35 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71 0 4.266 2.114 1.312 5.124.06A.75.75 0 0 1 6 .278"/></svg>
      </button>
      <button type="submit" class="btn btn-link btn-sm p-0 float-end">{'Logout'|t}</button>
    </form>
  </div>
</div>
