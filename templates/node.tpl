{include file="header.tpl"}
{$box_info}
<div class="card">
  <div class="card-body">
    <h6>node: <b>{$node}</b><h6>
    <h6>UUID: <b>{$domain_uuid}</b></h6>
    <br>
    {if $info['state']==1}
    <button type="button" class="btn btn-success" disabled>Running</button> 
        {if $readonly=="0"}
        <a href="node.php?node={$node}&state=stop" class="btn btn-info" role="button">STOP</a>
        <a href="node.php?node={$node}&state=destroy" class="btn btn-info" role="button">FORCE STOP</a>
        <a href="node.php?node={$node}&state=reboot" class="btn btn-info" role="button">REBOOT</a>
        <a href="node.php?node={$node}&state=suspend" class="btn btn-info" role="button">SUSPEND</a>
        {/if}
    {elseif $info['state']==3}
    <button type="button" class="btn btn-alert" disabled>Paused</button> 
        {if $readonly=="0"}
        <a href="node.php?node={$node}&state=resume" class="btn btn-info" role="button">RESUME</a>
        {/if}
    {elseif $info['state']==5}
    <button type="button" class="btn btn-danger" disabled>Shutoff</button> 
        {if $readonly=="0"}
        <a href="node.php?node={$node}&state=start" class="btn btn-info" role="button">START</a>
        {/if}
    {/if} 
    <br><br>
    <h6>maxMem: <b>{$info['maxMem']}</b> GB<h6>
    <h6>memory: <b>{$info['memory']}</b> GB</h6>
    <h6>vCPUs: <b>{$info['nrVirtCpu']}</b><h6>
    <h6>cpu Used: <b>{$info['cpuUsed']}</b></h6>
    <br>
    {if $info['state']==1}
    <h6>VNC: <b>{$vnc_ip}:{$vnc_port}</b></h6>
    {/if}
  </div>
</div>
{include file="footer.tpl"}
 
