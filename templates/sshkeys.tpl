{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header">{'SSH keys'|t}</div>
  <div class="card-body">
    <p class="small text-body-secondary">{'Saved keys can be selected when creating a machine from a cloud image.'|t}</p>
    <table class="table table-sm align-middle">
      <thead><tr><th>{'Name'|t}</th><th>{'Fingerprint'|t}</th><th>{'Created'|t}</th><th></th></tr></thead>
      <tbody>
{foreach $ssh_keys as $k}
        <tr>
          <td>{$k.name}</td>
          <td><code class="small" title="{$k.public_key}">{$k.fingerprint}</code></td>
          <td class="text-nowrap"><small>{$k.created_at|date_format:'%Y-%m-%d %H:%M'}</small></td>
          <td class="text-end">{call post_button url='sshkeys.php' action='delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['id'=>$k.id] confirm={'Remove SSH key %s?'|t:$k.name}}</td>
        </tr>
{foreachelse}
        <tr><td colspan="4">{'No saved keys yet.'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
    <form method="post" action="sshkeys.php">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="add">
      <div class="mb-2">
        <label class="form-label" for="public_key">{'Public key'|t}</label>
        <textarea class="form-control form-control-sm font-monospace" id="public_key" name="public_key" rows="2" placeholder="ssh-ed25519 AAAA... user@host" required></textarea>
      </div>
      <div class="d-flex gap-2" style="max-width: 480px;">
        <input type="text" name="name" class="form-control form-control-sm" placeholder="{'name (optional, default: key comment)'|t}" maxlength="{$ssh_key_name_max}">
        <button type="submit" class="btn btn-primary btn-sm text-nowrap">{'Add key'|t}</button>
      </div>
    </form>
  </div>
</div>
{include file="footer.tpl"}
