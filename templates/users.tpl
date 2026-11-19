{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header">{'Users'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'User'|t}</th><th>{'Role'|t}</th><th>{'Last login'|t}</th>{if $manage}<th>{'New password'|t}</th><th></th>{/if}</tr></thead>
    <tbody>
{foreach $users as $u}
      <tr>
        <td class="text-nowrap">{$u.username}{if $u.id==$self_id} <small class="text-body-secondary">({'you'|t})</small>{/if}</td>
        <td>
{if $manage}
          <form method="post" action="users.php" class="d-flex">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="action" value="role">
            <input type="hidden" name="id" value="{$u.id}">
            <select name="role" class="form-select form-select-sm w-auto me-1">
{foreach $roles as $r}
              <option value="{$r}"{if $u.role==$r} selected{/if}>{$r|t}</option>
{/foreach}
            </select>
            <button type="submit" class="btn btn-outline-secondary btn-sm">{'Save'|t}</button>
          </form>
{else}
          {$u.role|t}
{/if}
        </td>
        <td class="text-nowrap"><small>{if $u.last_login}{$u.last_login|date_format:'%Y-%m-%d %H:%M'}{else}-{/if}</small></td>
{if $manage}
        <td>
          <form method="post" action="users.php" class="d-flex">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="id" value="{$u.id}">
            <input type="password" name="password" class="form-control form-control-sm me-1" minlength="{$password_min}" autocomplete="new-password" required>
            <button type="submit" class="btn btn-outline-secondary btn-sm text-nowrap">{'Set'|t}</button>
          </form>
        </td>
        <td class="text-end">
{if $u.id!=$self_id}
          <form method="post" action="users.php" class="d-inline" data-confirm="{'Delete user %s?'|t:$u.username}">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="{$u.id}">
            <button type="submit" class="btn btn-outline-danger btn-sm">{'Delete'|t}</button>
          </form>
{/if}
        </td>
{/if}
      </tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>

{if $quota_users}
<div class="card mb-3">
  <div class="card-header">{'Quotas per hypervisor'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'User'|t}</th><th>{'Machines'|t}</th><th>{'vCPUs'|t}</th><th>{'Memory (MB)'|t}</th><th>{'Disk (GB)'|t}</th>{if $manage}<th></th>{/if}</tr></thead>
    <tbody>
{foreach $quota_users as $q}
      <tr>
        <td class="text-nowrap">{$q.username} <small class="text-body-secondary">({$q.role|t})</small></td>
{if $manage}
{foreach ['machines', 'vcpus', 'memory_mb', 'disk_gb'] as $key}
        <td>
          <input type="number" min="0" name="{$key}" form="quota-{$q.id}" value="{$q.quotas[$key]}" class="form-control form-control-sm" style="max-width: 8rem" placeholder="{'no limit'|t}">
          <small class="text-body-secondary">{'used: %d'|t:$q.usage[$key]}</small>
        </td>
{/foreach}
        <td class="text-end">
          <form method="post" action="users.php" id="quota-{$q.id}">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="action" value="quota">
            <input type="hidden" name="id" value="{$q.id}">
            <button type="submit" class="btn btn-outline-secondary btn-sm">{'Save'|t}</button>
          </form>
        </td>
{else}
{foreach ['machines', 'vcpus', 'memory_mb', 'disk_gb'] as $key}
        <td>{$q.usage[$key]} / {if $q.quotas[$key] === null}{'no limit'|t}{else}{$q.quotas[$key]}{/if}</td>
{/foreach}
{/if}
      </tr>
{/foreach}
    </tbody>
  </table>
  </div>
  <div class="card-footer text-body-secondary small">{'Usage is shown for the current hypervisor, the limits apply to each hypervisor. An empty field means no limit.'|t}</div>
</div>
{/if}

{if $manage}
<div class="card mb-3">
  <div class="card-header">{'New user'|t}</div>
  <div class="card-body">
    <form method="post" action="users.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="create">
      <div class="col-md-4">
        <label class="form-label" for="username">{'User'|t}</label>
        <input type="text" class="form-control" id="username" name="username" required pattern="[A-Za-z0-9._@\-]{ldelim}1,64{rdelim}" autocomplete="off">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="password">{'Password'|t}</label>
        <input type="password" class="form-control" id="password" name="password" minlength="{$password_min}" autocomplete="new-password" required>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="role">{'Role'|t}</label>
        <select class="form-select" id="role" name="role">
{foreach $roles as $r}
          <option value="{$r}"{if $r=='viewer'} selected{/if}>{$r|t}</option>
{/foreach}
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">{'Create'|t}</button>
      </div>
    </form>
  </div>
</div>
{/if}

<div class="card mb-3">
  <div class="card-header">{'Roles'|t}</div>
  <ul class="list-group list-group-flush small">
    <li class="list-group-item"><b>{'user'|t}</b>: {'own machines only: power actions, console, snapshots, editing; creates new machines within the quotas'|t}.</li>
    <li class="list-group-item"><b>{'localadmin'|t}</b>: {'like user, and may also delete own machines'|t}.</li>
    <li class="list-group-item"><b>{'viewer'|t}</b>: {'read-only access'|t}.</li>
    <li class="list-group-item"><b>{'auditor'|t}</b>: {'read-only access, the action log and the list of users'|t}.</li>
    <li class="list-group-item"><b>{'operator'|t}</b>: {'power actions, snapshots, console, creating and editing machines'|t}.</li>
    <li class="list-group-item"><b>{'admin'|t}</b>: {'everything, including deleting machines, storage and network management and users'|t}.</li>
  </ul>
</div>
{include file="footer.tpl"}
