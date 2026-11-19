{include file="header.tpl"}
<div class="card mb-3">
  <div class="card-header">{'Hypervisor connections'|t}</div>
  <div class="table-responsive">
  <table class="table table-sm align-middle mb-0">
    <thead><tr><th>{'Name'|t}</th><th>URI</th><th>{'Access'|t}</th><th>{'Source'|t}</th><th></th></tr></thead>
    <tbody>
{foreach $connection_list as $key => $c}
      <tr>
        <td>{$c.label}{if $key==$connection_key} <span class="badge text-bg-primary">{'current'|t}</span>{/if}</td>
        <td><code class="small">{$c.uri}</code>
{if isset($test_results[$key])}
          <div class="small {if $test_results[$key].ok}text-success{else}text-danger{/if}">{$test_results[$key].text}</div>
{/if}
        </td>
        <td>{if $c.readonly}{'read-only'|t}{else}{'full'|t}{/if}</td>
        <td><span class="badge {if $c.source=='config'}text-bg-light border{else}text-bg-info{/if}">{if $c.source=='config'}config.php{else}{'panel'|t}{/if}</span></td>
        <td class="text-end text-nowrap">
          {call post_button url='connections.php' action='test' label={'Test'|t} extra=['key'=>$key]}
{if $c.source=='panel'}
          {call post_button url='connections.php' action='delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['key'=>$key] confirm={'Remove connection %s? Machines on that host are not affected.'|t:$c.label}}
{/if}
        </td>
      </tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
<div class="card mb-3">
  <div class="card-header">{'Add connection'|t}</div>
  <div class="card-body">
    <form method="post" action="connections.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="add">
      <div class="col-md-3">
        <label class="form-label" for="conn-label">{'Name'|t}</label>
        <input type="text" class="form-control form-control-sm" id="conn-label" name="label" maxlength="64" required placeholder="kvm2">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="conn-uri">URI</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="conn-uri" name="uri" required placeholder="qemu+ssh://root@kvm2.example.com/system">
      </div>
      <div class="col-md-1">
        <div class="form-check"><input type="checkbox" class="form-check-input" id="conn-ro" name="readonly" value="1"> <label class="form-check-label small" for="conn-ro">{'read-only'|t}</label></div>
      </div>
      <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">{'Add'|t}</button></div>
      <div class="col-12 form-text">{'Remote hosts: qemu+ssh:// needs an SSH key of the panel user (www-data, in Docker inside the container) authorized on the target host; ?no_verify=1 skips the host key check. The consoles work only for local connections.'|t}</div>
    </form>
  </div>
</div>
{include file="footer.tpl"}
