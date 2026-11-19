{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>{'Cloud image catalog'|t}</span>
    <a href="cloud.php" class="small">{'Back to cloud images'|t}</a>
  </div>
  <div class="card-body">
    <p class="small mb-1">{'List'|t}: <code class="user-select-all">{$catalog_url}</code></p>
    <p class="small mb-2 text-body-secondary">
{if $last_sync}
      {'Last synchronization'|t}: {$last_sync.time|date_format:'%Y-%m-%d %H:%M'} -
      {if $last_sync.ok}<span class="text-success">{$last_sync.message}</span>{else}<span class="text-danger">{$last_sync.message}</span>{/if}
{else}
      {'Not synchronized yet.'|t}
{/if}
      {if $auto_sync}{'Synchronized automatically once a day.'|t}{else}{'Automatic synchronization is disabled.'|t}{/if}
      {'Synchronization adds new images and updates unchanged ones; it never removes anything.'|t}
    </p>
    {call post_button url='cloud-catalog.php' action='sync' label={'Synchronize now'|t} class='btn-primary btn-sm'}
  </div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'Key'|t}</th><th>{'Name'|t}</th><th>URL</th><th>{'Source'|t}</th><th></th></tr></thead>
    <tbody>
{foreach $rows as $r}
      <tr{if !$r.enabled} class="text-body-secondary"{/if}>
        <td><code>{$r.key}</code></td>
        <td>{$r.label}{if isset($downloaded[$r.key])} <span class="badge text-bg-success">{'downloaded'|t}</span>{/if}{if !$r.enabled} <span class="badge text-bg-secondary">{'disabled'|t}</span>{/if}</td>
        <td class="text-truncate" style="max-width: 22rem;"><small><a href="{$r.url}" rel="noreferrer" title="{$r.url}">{$r.url}</a></small></td>
        <td class="text-nowrap">
{if $r.source=='local'}
          <span class="badge text-bg-info">{'added in the panel'|t}</span>
{elseif $r.modified}
          <span class="badge text-bg-warning">{'list, edited'|t}</span>
{else}
          <span class="badge text-bg-light border">{'list'|t}</span>
{/if}
        </td>
        <td class="text-end text-nowrap">
          <a href="cloud-catalog.php?edit={$r.key|escape:'url'}#image-form" class="btn btn-outline-secondary btn-sm">{'Edit'|t}</a>
{if $r.source=='list' && $r.modified}
          {call post_button url='cloud-catalog.php' action='reset' label={'Restore from list'|t} extra=['key'=>$r.key]}
{/if}
{if $r.enabled}
          {call post_button url='cloud-catalog.php' action='disable' label={'Disable'|t} extra=['key'=>$r.key]}
{else}
          {call post_button url='cloud-catalog.php' action='enable' label={'Enable'|t} extra=['key'=>$r.key]}
{/if}
{if $r.source=='local'}
          {call post_button url='cloud-catalog.php' action='delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['key'=>$r.key] confirm={'Remove %s from the catalog? A downloaded image stays in its storage pool.'|t:$r.key}}
{/if}
        </td>
      </tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>

<div class="card mb-3" id="image-form">
  <div class="card-header">{if $form.new}{'Add image'|t}{else}{'Edit image'|t}: {$form.key}{/if}</div>
  <div class="card-body">
{foreach $errors as $e}
    <div class="alert alert-danger py-2">{$e}</div>
{/foreach}
    <form method="post" action="cloud-catalog.php" style="max-width: 720px;">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="new" value="{$form.new}">
      <div class="row g-2">
        <div class="col-md-4 mb-2">
          <label class="form-label" for="key">{'Key'|t}</label>
          {if $form.new}
          <input type="text" class="form-control form-control-sm font-monospace" id="key" name="key" value="{$form.key}" placeholder="fedora-43" pattern="[a-z0-9][a-z0-9._\-]{ldelim}0,63{rdelim}" required>
          {else}
          <input type="text" class="form-control form-control-sm font-monospace" id="key" value="{$form.key}" disabled>
          <input type="hidden" name="key" value="{$form.key}">
          {/if}
        </div>
        <div class="col-md-8 mb-2">
          <label class="form-label" for="label">{'Name'|t}</label>
          <input type="text" class="form-control form-control-sm" id="label" name="label" value="{$form.label}" placeholder="Fedora 43" maxlength="64" required>
        </div>
        <div class="col-12 mb-2">
          <label class="form-label" for="url">{'Image URL (qcow2 or raw, https)'|t}</label>
          <input type="url" class="form-control form-control-sm font-monospace" id="url" name="url" value="{$form.url}" placeholder="https://.../Fedora-Cloud-Base-43.x86_64.qcow2" required>
          <div class="form-text">{'The image must support cloud-init (NoCloud). The key cannot be changed later; a downloaded image is found by it.'|t}</div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">{'Save'|t}</button>
{if !$form.new}
      <a href="cloud-catalog.php" class="btn btn-link btn-sm">{'Cancel'|t}</a>
{/if}
    </form>
  </div>
</div>
{include file="footer.tpl"}
