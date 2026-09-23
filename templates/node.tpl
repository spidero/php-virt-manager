{include file="header.tpl"}
{* action button posting to node.php; $confirm adds a JS confirmation *}
{function name=action_button state='' label='' class='btn-info' confirm=''}
<form method="post" action="node.php" class="d-inline"{if $confirm} onsubmit="return confirm('{$confirm|escape:'javascript'}');"{/if}>
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="node" value="{$node}">
  <input type="hidden" name="state" value="{$state}">
  <button type="submit" class="btn {$class}">{$label}</button>
</form>
{/function}
<div class="card">
  <div class="card-body">
    <h6>node: <b>{$node}</b></h6>
    <h6>UUID: <b>{$domain_uuid}</b></h6>
    <br>
    {if $info['state']==1}
    <button type="button" class="btn btn-success" disabled>Running</button>
        {if !$readonly}
        {call action_button state='stop' label='STOP'}
        {call action_button state='destroy' label='FORCE STOP' class='btn-danger' confirm="Force stop `$node`? Unsaved data in the machine will be lost."}
        {call action_button state='reboot' label='REBOOT'}
        {call action_button state='suspend' label='SUSPEND'}
        {/if}
    {elseif $info['state']==3}
    <button type="button" class="btn btn-warning" disabled>Paused</button>
        {if !$readonly}
        {call action_button state='resume' label='RESUME'}
        {/if}
    {elseif $info['state']==5}
    <button type="button" class="btn btn-danger" disabled>Shutoff</button>
        {if !$readonly}
        {call action_button state='start' label='START'}
        {/if}
    {/if}
    <br><br>
    <h6>maxMem: <b>{$info['maxMem']}</b> GB</h6>
    <h6>memory: <b>{$info['memory']}</b> GB</h6>
    <h6>vCPUs: <b>{$info['nrVirtCpu']}</b></h6>
    <h6>cpu Used: <b>{$info['cpuUsed']}</b></h6>
    <br>
    {if $info['state']==1}
    <h6>VNC: <b>{$vnc_ip}:{$vnc_port}</b></h6>
    {/if}
  </div>
</div>
{include file="footer.tpl"}
