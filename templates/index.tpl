{include file="header.tpl"}
{if $update_info}
<div class="alert alert-warning d-flex justify-content-between align-items-center" role="status">
  <span>{'A new version of php-virt-manager is available: %s.'|t:$update_info.version}</span>
  <a href="{$update_info.url}" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener">{'What is new'|t}</a>
</div>
{/if}
{if $show_contact}
<div class="alert alert-info d-flex align-items-start gap-3 mb-3" role="note">
  <div class="flex-grow-1">
    <div class="fw-semibold mb-1">{'Ideas, bugs, questions?'|t}</div>
    <div class="small mb-2">{'php-virt-manager is developed in the open on GitHub - suggest new features, report bugs or ask about anything else there.'|t}</div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-outline-primary" href="{$repo_url}/issues/new?template=feature_request.yml" target="_blank" rel="noopener">{'Suggest a feature'|t}</a>
      <a class="btn btn-sm btn-outline-primary" href="{$repo_url}/issues/new?template=bug_report.yml" target="_blank" rel="noopener">{'Report a bug'|t}</a>
      <a class="btn btn-sm btn-outline-secondary" href="{$repo_url}/security/advisories/new" target="_blank" rel="noopener">{'Security issue (private)'|t}</a>
      <a class="btn btn-sm btn-outline-secondary" href="https://github.com/spidero" target="_blank" rel="noopener">{'Other: contact the author'|t}</a>
      <a class="btn btn-sm btn-outline-success" href="https://github.com/sponsors/spidero" target="_blank" rel="noopener" title="{'A voluntary donation to the author, not a payment for features or support.'|t}">{'Support the project (GitHub Sponsors)'|t}</a>
    </div>
  </div>
  <form method="post" action="dismiss.php" class="m-0">
    <input type="hidden" name="csrf" value="{$csrf_token}">
    <input type="hidden" name="notice" value="contact">
    <button type="submit" class="btn-close" aria-label="{'Close'|t}" title="{'Close (it will not be shown again)'|t}"></button>
  </form>
</div>
{/if}
<div class="row">
{foreach [['running','success',1],['paused','warning',3],['shut off','danger',5]] as $s}
  <div class="col-4 mb-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-{$s[1]}" data-count-state="{$s[2]}">{$state_counts[$s[0]]|default:0}</div>
        <div class="text-body-secondary">{$s[0]|t}</div>
      </div>
    </div>
  </div>
{/foreach}
</div>
{if $can.view}
<div class="card">
  <div class="card-header">{'Hypervisor'|t}</div>
  <table class="table table-sm mb-0">
    <tr><th style="width: 30%">{'Connection'|t}</th><td>{$connection}</td></tr>
    <tr><th>{'Version'|t}</th><td>{$get_hypervisor['hypervisor_string']}</td></tr>
    <tr><th>{'Architecture'|t}</th><td>{$node_info['model']}</td></tr>
    <tr><th>{'Memory'|t}</th><td>
      {'%s used of %s, %s available'|t:$memory.used_text:$memory.total_text:$memory.available_text}
      <div class="progress mt-1" role="progressbar" aria-label="{'Memory'|t}" aria-valuenow="{$memory.percent}" aria-valuemin="0" aria-valuemax="100" style="height: 6px; max-width: 320px;">
        <div class="progress-bar{if $memory.percent >= 90} bg-danger{elseif $memory.percent >= 75} bg-warning{/if}" style="width: {$memory.percent}%"></div>
      </div>
    </td></tr>
{if $cpu_model}
    <tr><th>{'Processor'|t}</th><td>{$cpu_model}</td></tr>
{/if}
    <tr><th>{'CPUs'|t}</th><td>{'%d (%d socket, %d cores, %d MHz)'|t:$node_info['cpus']:$node_info['sockets']:$node_info['cores']:$node_info['mhz']}</td></tr>
    <tr><th>{'NUMA nodes'|t}</th><td>{$node_info['nodes']}</td></tr>
  </table>
</div>

<div class="card mt-3">
  <div class="card-header">{'Host load'|t} <small class="text-body-secondary">({'last 5 minutes'|t})</small></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="host_cpu" data-labels="CPU" data-unit="%"></canvas></div>
      <div class="col-md-6"><canvas class="w-100" style="height: 110px" data-series="host_mem" data-labels="{'Memory used'|t}" data-unit="GB"></canvas></div>
    </div>
  </div>
</div>
{else}
<div class="card">
  <div class="card-header">{'My quota on this hypervisor'|t}</div>
  <table class="table table-sm mb-0">
{foreach [['machines', 'Machines'], ['vcpus', 'vCPUs'], ['memory_mb', 'Memory (MB)'], ['disk_gb', 'Disk (GB)']] as $q}
    <tr><th style="width: 30%">{$q[1]|t}</th><td>{$usage[$q[0]]} / {if $quotas[$q[0]] === null}{'no limit'|t}{else}{$quotas[$q[0]]}{/if}</td></tr>
{/foreach}
  </table>
</div>
{/if}

{* bar color by usage: green, then yellow from 75 %, red from 90 % *}
{function name=usage_bar pct=0 label=''}
<div class="progress vm-bar" role="progressbar" aria-label="{$label}" aria-valuenow="{$pct}" aria-valuemin="0" aria-valuemax="100">
  <div class="progress-bar{if $pct >= 90} bg-danger{elseif $pct >= 75} bg-warning{else} bg-success{/if}" style="width: {$pct}%"></div>
</div>
{/function}
<div class="card mt-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>{'Machines'|t}</span>
    <small class="text-body-secondary">{count($vms)}</small>
  </div>
  <div class="table-responsive">
  <table class="table table-hover align-middle mb-0 vm-table">
    <thead class="small text-body-secondary">
      <tr><th>{'Name'|t}</th><th>{'State'|t}</th><th>IP</th><th>CPU</th><th>{'Memory'|t}</th><th>{'Disk'|t}</th></tr>
    </thead>
    <tbody>
{foreach $vms as $vm}
      <tr data-vm="{$vm.name}" data-vm-state="{$vm.id}">
        <td class="text-nowrap">
          {include file="star.tpl" name=$vm.name back='index'}
          <a href="node.php?node={$vm.name|escape:'url'}" class="fw-semibold text-decoration-none ms-1">{$vm.name}</a>
          <div class="small text-body-secondary vm-sub">{if $vm.title}{$vm.title} - {/if}{'%d vCPU'|t:$vm.vcpus}</div>
        </td>
        <td><span class="badge rounded-pill text-bg-{$vm.color}" data-vm-badge>{$vm.label|t}</span></td>
        <td class="small text-nowrap">
{foreach $vm.addresses as $a}
          <div><span class="user-select-all font-monospace">{$a.addr}</span></div>
{foreachelse}
          <span class="text-body-secondary">-</span>
{/foreach}
        </td>
        <td class="vm-usage">
{if $vm.active}
          <div class="small vm-num" data-vm-cpu-text>…</div>
          {call usage_bar pct=0 label='CPU'}
{else}
          <span class="text-body-secondary">-</span>
{/if}
        </td>
        <td class="vm-usage">
          <div class="small vm-num" data-vm-mem-text data-of="{'%s of %s'|t:'%1':'%2'}"{if $vm.mem_source} title="{$mem_titles[$vm.mem_source]}"{/if}>{$vm.mem_text}</div>
{if $vm.active && $vm.mem_source}
          {call usage_bar pct=$vm.mem_pct label={'Memory'|t}}
{/if}
        </td>
        <td class="vm-usage">
{if $vm.disk_text}
          <div class="small vm-num">{$vm.disk_text}</div>
          {call usage_bar pct=$vm.disk_pct label={'Disk'|t}}
{else}
          <span class="text-body-secondary">-</span>
{/if}
        </td>
      </tr>
{foreachelse}
      <tr><td colspan="6" class="text-body-secondary">{'No machines found'|t}</td></tr>
{/foreach}
    </tbody>
  </table>
  </div>
</div>
{include file="footer.tpl"}
