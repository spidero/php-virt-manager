{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header">{'Profile'|t}: {$user.username} <small class="text-body-secondary">({$user.role|t})</small></div>
  <div class="card-body">
    <form method="post" action="profile.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="lang">
      <div class="col-md-4">
        <label class="form-label" for="lang">{'Language'|t}</label>
        <select class="form-select" id="lang" name="lang">
{foreach $languages as $code => $name}
          <option value="{$code}"{if $code==$lang} selected{/if}>{$name}</option>
{/foreach}
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">{'Save'|t}</button>
      </div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">{'Change password'|t}</div>
  <div class="card-body">
    <form method="post" action="profile.php" style="max-width: 360px;">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="password">
      <div class="mb-3">
        <label class="form-label" for="current">{'Current password'|t}</label>
        <input type="password" class="form-control" id="current" name="current" autocomplete="current-password" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">{'New password'|t}</label>
        <input type="password" class="form-control" id="password" name="password" minlength="{$password_min}" autocomplete="new-password" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password2">{'Repeat new password'|t}</label>
        <input type="password" class="form-control" id="password2" name="password2" minlength="{$password_min}" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn btn-primary">{'Change password'|t}</button>
    </form>
  </div>
</div>
<div class="card mb-3">
  <div class="card-header">{'API tokens'|t}</div>
  <div class="card-body">
{if $new_token}
    <div class="alert alert-warning">
      {'New token'|t}: <code class="user-select-all">{$new_token}</code>
    </div>
{/if}
    <p class="small text-body-secondary">{'Tokens act with your role (%s). Example:'|t:({$user.role|t})}
      <code class="d-block mt-1">curl -H "Authorization: Bearer pvm_..." {$api_url}/domains</code>
    </p>
    <table class="table table-sm align-middle">
      <thead><tr><th>{'Name'|t}</th><th>{'Token'|t}</th><th>{'Created'|t}</th><th>{'Last used'|t}</th><th></th></tr></thead>
      <tbody>
{foreach $tokens as $tk}
        <tr>
          <td>{$tk.name}</td><td><code>{$tk.prefix}...</code></td>
          <td class="text-nowrap"><small>{$tk.created_at|date_format:'%Y-%m-%d %H:%M'}</small></td>
          <td class="text-nowrap"><small>{if $tk.last_used}{$tk.last_used|date_format:'%Y-%m-%d %H:%M'}{else}-{/if}</small></td>
          <td class="text-end">{call post_button url='profile.php' action='token_delete' label={'Revoke'|t} class='btn-outline-danger btn-sm' extra=['id'=>$tk.id] confirm={'Revoke token %s?'|t:$tk.name}}</td>
        </tr>
{foreachelse}
        <tr><td colspan="5">{'No tokens'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
    <form method="post" action="profile.php" class="d-flex gap-2" style="max-width: 480px;">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="token_create">
      <input type="text" name="name" class="form-control form-control-sm" placeholder="{'token name, e.g. ansible'|t}" maxlength="64" required>
      <button type="submit" class="btn btn-primary btn-sm text-nowrap">{'Create token'|t}</button>
    </form>
  </div>
</div>
{include file="footer.tpl"}
