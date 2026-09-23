{include file="header.tpl"}
<div class="card">
  <div class="card-header">New machine</div>
  <div class="card-body">
{if $errors}
    <div class="alert alert-danger">{foreach $errors as $e}{$e}<br>{/foreach}</div>
{/if}
{if !$pools}
    <div class="alert alert-warning">No active storage pool - start a pool first.</div>
{/if}
    <form method="post" action="create.php">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <div class="form-group">
        <label for="name">Name</label>
        <input type="text" class="form-control" id="name" name="name" value="{$form.name}" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}">
      </div>
      <div class="form-row">
        <div class="form-group col-md-4">
          <label for="memory">Memory (MB)</label>
          <input type="number" class="form-control" id="memory" name="memory" value="{$form.memory}" min="256" max="{$max_memory_mb}" step="256" required>
        </div>
        <div class="form-group col-md-4">
          <label for="vcpus">vCPUs</label>
          <input type="number" class="form-control" id="vcpus" name="vcpus" value="{$form.vcpus}" min="1" max="{$max_vcpus}" required>
        </div>
        <div class="form-group col-md-4">
          <label for="disk">Disk (GB, qcow2)</label>
          <input type="number" class="form-control" id="disk" name="disk" value="{$form.disk}" min="1" max="4096" required>
        </div>
      </div>
      <div class="form-group">
        <label for="pool">Disk storage pool</label>
        <select class="form-control" id="pool" name="pool">
{foreach $pools as $name => $free}
          <option value="{$name}"{if $form.pool==$name} selected{/if}>{$name} ({$free})</option>
{/foreach}
        </select>
      </div>
      <div class="form-group">
        <label for="iso">Installation ISO</label>
        <select class="form-control" id="iso" name="iso">
          <option value="">- none -</option>
{foreach $isos as $path => $label}
          <option value="{$path}"{if $form.iso==$path} selected{/if}>{$label}</option>
{/foreach}
        </select>
      </div>
      <div class="form-group">
        <label for="network">Network</label>
        <select class="form-control" id="network" name="network">
{foreach $networks as $n}
          <option value="{$n}"{if $form.network==$n} selected{/if}>{$n}</option>
{/foreach}
        </select>
      </div>
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="start" name="start" value="1"{if $form.start} checked{/if}>
        <label class="form-check-label" for="start">Start after creation</label>
      </div>
      <p class="text-muted"><small>q35, host-passthrough CPU, virtio disk and network, VNC graphics on localhost.</small></p>
      <button type="submit" class="btn btn-primary">Create</button>
    </form>
  </div>
</div>
{include file="footer.tpl"}
