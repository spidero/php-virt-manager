{include file="header.tpl"}
{if $allow.admin}
<div class="card mb-3">
  <div class="card-header">{'New network'|t}</div>
  <div class="card-body">
    <form method="post" action="network.php" class="row g-2 align-items-end">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="create">
      <div class="col-md-3">
        <label class="form-label" for="net-name">{'Name'|t}</label>
        <input type="text" class="form-control form-control-sm" id="net-name" name="network" required pattern="[A-Za-z0-9][A-Za-z0-9._\-]{ldelim}0,63{rdelim}" placeholder="lab">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="net-mode">{'Type'|t}</label>
        <select class="form-select form-select-sm" id="net-mode" name="mode">
{foreach $network_modes as $m => $label}
          <option value="{$m}">{$label|t}</option>
{/foreach}
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="net-subnet">{'Subnet'|t}</label>
        <input type="text" class="form-control form-control-sm font-monospace" id="net-subnet" name="subnet" required placeholder="192.168.50.0/24">
      </div>
      <div class="col-md-2">
        <div class="form-check"><input type="checkbox" class="form-check-input" id="net-dhcp" name="dhcp" value="1" checked> <label class="form-check-label" for="net-dhcp">DHCP</label></div>
        <div class="form-check"><input type="checkbox" class="form-check-input" id="net-auto" name="autostart" value="1" checked> <label class="form-check-label" for="net-auto">{'autostart'|t}</label></div>
      </div>
      <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">{'Create'|t}</button></div>
      <div class="col-12 form-text">{'NAT: machines reach the outside through the host. Routed: the subnet is routed by the host (the router needs a route back). Isolated: machines and host only.'|t}</div>
    </form>
  </div>
</div>
{/if}
{foreach from=$networks item=n}
<div class="card mb-3">
  <div class="card-header">
    <b>{$n.name}</b>
    <span class="badge text-bg-{if $n.active}success{else}danger{/if}">{if $n.active}{'active'|t}{else}{'inactive'|t}{/if}</span>
    {if $n.autostart}<span class="badge text-bg-info">{'autostart'|t}</span>{/if}
{if $allow.admin}
    <span class="float-end">
    {if $n.active}
      {call post_button url='network.php' action='stop' label={'Stop'|t} extra=['network'=>$n.name] confirm={'Stop network %s? Machines connected to it lose connectivity.'|t:$n.name}}
    {else}
      {call post_button url='network.php' action='start' label={'Start'|t} class='btn-outline-success btn-sm' extra=['network'=>$n.name]}
    {/if}
      {call post_button url='network.php' action='autostart' label=($n.autostart)?{'Disable autostart'|t}:{'Enable autostart'|t} extra=['network'=>$n.name, 'enable'=>($n.autostart)?'0':'1']}
      {call post_button url='network.php' action='delete' label={'Delete'|t} class='btn-outline-danger btn-sm' extra=['network'=>$n.name] confirm={'Delete network %s?'|t:$n.name}}
    </span>
{/if}
  </div>
  <div class="card-body">
    <table class="table table-sm">
      <tr><th style="width: 30%">{'Bridge'|t}</th><td>{$n.bridge|default:'-'}</td></tr>
      <tr><th>{'Forwarding'|t}</th><td>{$n.forwarding|default:'-'}</td></tr>
      <tr><th>{'Network'|t}</th><td>{$n.ip_range|default:'-'}</td></tr>
      <tr><th>{'Gateway'|t}</th><td>{$n.gateway|default:'-'}</td></tr>
      <tr><th>{'DHCP range'|t}</th><td>{$n.dhcp|default:'-'}</td></tr>
    </table>
{if $n.ipv4 && $allow.admin}
    <form method="post" action="network.php" class="d-flex flex-wrap gap-1 align-items-center mb-3">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="dhcp_range">
      <input type="hidden" name="network" value="{$n.name}">
      <span class="small me-1">{'DHCP range'|t}:</span>
      <input type="text" class="form-control form-control-sm font-monospace w-auto" name="start" value="{$n.ipv4.range.start|default:''}" size="15" aria-label="{'from'|t}" required>
      <span>-</span>
      <input type="text" class="form-control form-control-sm font-monospace w-auto" name="end" value="{$n.ipv4.range.end|default:''}" size="15" aria-label="{'to'|t}" required>
      <button type="submit" class="btn btn-outline-primary btn-sm">{'Save'|t}</button>
    </form>
{/if}
{if $n.ipv4}
    <h6>{'Static addresses'|t}</h6>
    <table class="table table-sm mb-2">
      <thead><tr><th>MAC</th><th>IP</th><th>{'Hostname'|t}</th><th>{'Machine'|t}</th><th></th></tr></thead>
      <tbody>
{foreach $n.ipv4.hosts as $h}
        <tr>
          <td><code>{$h.mac}</code></td><td><span class="user-select-all">{$h.ip}</span></td><td>{$h.name|default:'-'}</td>
          <td>{$n.machines[$h.mac]|default:'-'}</td>
          <td class="text-end">{if $allow.admin}{call post_button url='network.php' action='host_delete' label={'Remove'|t} class='btn-outline-danger btn-sm' extra=['network'=>$n.name, 'mac'=>$h.mac]}{/if}</td>
        </tr>
{foreachelse}
        <tr><td colspan="5" class="text-body-secondary">{'None'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
{if $allow.admin}
    <form method="post" action="network.php" class="d-flex flex-wrap gap-1 mb-3">
      <input type="hidden" name="csrf" value="{$csrf_token}">
      <input type="hidden" name="action" value="host_add">
      <input type="hidden" name="network" value="{$n.name}">
      <input type="text" class="form-control form-control-sm font-monospace w-auto" name="mac" list="macs-{$n@index}" placeholder="52:54:00:..." size="17" aria-label="MAC" required>
      <datalist id="macs-{$n@index}">
{foreach $n.machines as $mac => $vm}
        <option value="{$mac}">{$vm}</option>
{/foreach}
      </datalist>
      <input type="text" class="form-control form-control-sm font-monospace w-auto" name="ip" placeholder="IP" size="15" aria-label="IP" required>
      <input type="text" class="form-control form-control-sm w-auto" name="hostname" placeholder="{'host name (optional)'|t}" aria-label="{'Hostname'|t}">
      <button type="submit" class="btn btn-outline-primary btn-sm">{'Add'|t}</button>
    </form>
{/if}
{/if}
    <h6>{'DHCP leases'|t}</h6>
    <table class="table table-sm mb-0">
      <thead><tr><th>IP</th><th>{'Mask'|t}</th><th>MAC</th><th>{'Hostname'|t}</th><th>{'Expires'|t}</th></tr></thead>
      <tbody>
{foreach from=$n.leases item=l}
        <tr><td><span class="user-select-all">{$l.ip}</span></td><td class="text-body-secondary">{$l.prefix|default:'-'}</td><td><code>{$l.mac}</code></td><td>{$l.hostname|default:'-'}</td><td>{$l.expires}</td></tr>
{foreachelse}
        <tr><td colspan="5">{'No leases'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
  </div>
</div>
{foreachelse}
<div class="card"><div class="card-body">{'No networks found'|t}</div></div>
{/foreach}
{include file="footer.tpl"}
