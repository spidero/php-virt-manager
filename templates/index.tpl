{include file="header.tpl"}
<div class="card">
  <div class="card-body">
    <h6>Connected: <b>{$connection}</b><h6>
    <br>
    <h5>Hypervisor info:</h5>
    <h6>Version: <b>{$get_hypervisor['hypervisor_string']}</b></h6>
    <h6>Architecture: <b>{$node_info['model']}</b></h6>
    <h6>Memory: <b>{$node_info['memory']}</b> GB</h6>
    <h6>CPUs: <b>{$node_info['cpus']}</b></h6>
    <h6>Nodes: <b>{$node_info['nodes']}</b></h6>
    <h6>Sockets: <b>{$node_info['sockets']}</b></h6>
    <h6>Cores: <b>{$node_info['cores']}</b></h6>
    <h6>MHz: <b>{$node_info['mhz']}</b></h6>
    <hr>
    <a href="storage.php">Storage</a>
  </div>
</div>
{include file="footer.tpl"}
