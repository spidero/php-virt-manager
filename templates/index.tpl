{include file="header.tpl"}
<div class="row">
{foreach [['running','success'],['paused','warning'],['shut off','danger']] as $s}
  <div class="col-4 mb-3">
    <div class="card text-center">
      <div class="card-body py-3">
        <div class="fs-2 fw-bold text-{$s[1]}">{$state_counts[$s[0]]|default:0}</div>
        <div class="text-body-secondary">{$s[0]}</div>
      </div>
    </div>
  </div>
{/foreach}
</div>
<div class="card">
  <div class="card-header">Hypervisor</div>
  <table class="table table-sm mb-0">
    <tr><th style="width: 30%">Connection</th><td>{$connection}</td></tr>
    <tr><th>Version</th><td>{$get_hypervisor['hypervisor_string']}</td></tr>
    <tr><th>Architecture</th><td>{$node_info['model']}</td></tr>
    <tr><th>Memory</th><td>{$node_info['memory']} GB</td></tr>
    <tr><th>CPUs</th><td>{$node_info['cpus']} ({$node_info['sockets']} socket, {$node_info['cores']} cores, {$node_info['mhz']} MHz)</td></tr>
    <tr><th>NUMA nodes</th><td>{$node_info['nodes']}</td></tr>
  </table>
</div>
{include file="footer.tpl"}
