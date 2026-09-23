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
{include file="footer.tpl"}
