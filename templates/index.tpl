{include file="header.tpl"}
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
{include file="footer.tpl"}
