{include file="header.tpl"}
<div class="card mx-auto" style="max-width: 360px;">
  <div class="card-header">PHP virt-manager - {'login'|t}</div>
  <div class="card-body">
{if $error}
    <div class="alert alert-danger" role="alert">{$error}</div>
{/if}
    <form method="post" action="login.php">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <div class="mb-3">
        <label class="form-label" for="user">{'User'|t}</label>
        <input type="text" class="form-control" id="user" name="user" autocomplete="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">{'Password'|t}</label>
        <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary">{'Login'|t}</button>
    </form>
  </div>
</div>
{include file="footer.tpl"}
