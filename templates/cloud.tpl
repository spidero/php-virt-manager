{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header">{'Cloud images'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'Image'|t}</th><th>{'State'|t}</th><th></th></tr></thead>
    <tbody>
{foreach $catalog as $key => $img}
      <tr>
        <td>{$img.label}</td>
        <td>
{if isset($downloaded[$key])}
          <span class="badge text-bg-success">{'downloaded'|t}</span> <small class="text-body-secondary">{$downloaded[$key].pool}, {$downloaded[$key].size}</small>
{else}
          <span class="badge text-bg-secondary">{'not downloaded'|t}</span>
{/if}
        </td>
        <td class="text-end">
{if !isset($downloaded[$key]) && $allow.admin && $pools}
          <form method="post" action="cloud.php" class="d-inline-flex gap-1">
            <input type="hidden" name="csrf" value="{$csrf_token}">
            <input type="hidden" name="action" value="download">
            <input type="hidden" name="image" value="{$key}">
            <select name="pool" class="form-select form-select-sm w-auto" aria-label="{'Storage pool'|t}">
{foreach $pools as $name => $free}
              <option value="{$name}">{$name} ({$free})</option>
{/foreach}
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm text-nowrap">{'Download'|t}</button>
          </form>
{/if}
        </td>
      </tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">{'New machine from a cloud image'|t}</div>
  <div class="card-body">
{if !$iso_tool}
    <div class="alert alert-warning">{'xorriso or genisoimage is required to build the cloud-init ISO.'|t}</div>
{/if}
{if $errors}
    <div class="alert alert-danger">{foreach $errors as $e}{$e}<br>{/foreach}</div>
{/if}
{if !$downloaded}
    <p class="text-body-secondary mb-0">{'Download an image first.'|t}</p>
{else}
    <form method="post" action="cloud.php">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="create">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label" for="image">{'Image'|t}</label>
          <select class="form-select" id="image" name="image">
{foreach $downloaded as $key => $d}
            <option value="{$key}"{if $form.image==$key} selected{/if}>{$catalog[$key].label}</option>
{/foreach}
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="name">{'Name'|t} ({'also the hostname'|t})</label>
          <input type="text" class="form-control" id="name" name="name" value="{$form.name}" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="memory">{'Memory (MB)'|t}</label>
          <input type="number" class="form-control" id="memory" name="memory" value="{$form.memory}" min="256" max="{$max_memory_mb}" step="256" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="vcpus">{'vCPUs'|t}</label>
          <input type="number" class="form-control" id="vcpus" name="vcpus" value="{$form.vcpus}" min="1" max="{$max_vcpus}" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label" for="disk">{'Disk (GB, qcow2)'|t}</label>
          <input type="number" class="form-control" id="disk" name="disk" value="{$form.disk}" min="3" max="4096" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="pool">{'Disk storage pool'|t}</label>
          <select class="form-select" id="pool" name="pool">
{foreach $pools as $name => $free}
            <option value="{$name}"{if $form.pool==$name} selected{/if}>{$name} ({$free})</option>
{/foreach}
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="network">{'Network'|t}</label>
          <select class="form-select" id="network" name="network">
{foreach $networks as $n}
            <option value="{$n}"{if $form.network==$n} selected{/if}>{$n}</option>
{/foreach}
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="user">{'User in the machine'|t}</label>
          <input type="text" class="form-control" id="user" name="user" value="{$form.user}" required pattern="[a-z_][a-z0-9_\-]{ldelim}0,31{rdelim}">
          <div class="form-text">{'Gets sudo without a password.'|t}</div>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label" for="password">{'Password'|t} ({'optional'|t})</label>
          <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
          <div class="form-text">{'Enables password login over SSH and on the console.'|t}</div>
        </div>
        <div class="col-12 mb-3">
          <label class="form-label" for="ssh_keys">{'SSH public keys'|t} ({'one per line'|t})</label>
          <textarea class="form-control font-monospace small" id="ssh_keys" name="ssh_keys" rows="3" placeholder="ssh-ed25519 AAAA... user@host">{$form.ssh_keys}</textarea>
        </div>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="start" name="start" value="1"{if $form.start} checked{/if}>
        <label class="form-check-label" for="start">{'Start after creation'|t}</label>
      </div>
      <button type="submit" class="btn btn-primary">{'Create'|t}</button>
    </form>
{/if}
  </div>
</div>
{include file="footer.tpl"}
