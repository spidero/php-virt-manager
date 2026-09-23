{include file="header.tpl"}
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
    <h6>{'DHCP leases'|t}</h6>
    <table class="table table-sm mb-0">
      <thead><tr><th>IP</th><th>MAC</th><th>{'Hostname'|t}</th><th>{'Expires'|t}</th></tr></thead>
      <tbody>
{foreach from=$n.leases item=l}
        <tr><td>{$l.ip}</td><td><code>{$l.mac}</code></td><td>{$l.hostname|default:'-'}</td><td>{$l.expires}</td></tr>
{foreachelse}
        <tr><td colspan="4">{'No leases'|t}</td></tr>
{/foreach}
      </tbody>
    </table>
  </div>
</div>
{foreachelse}
<div class="card"><div class="card-body">{'No networks found'|t}</div></div>
{/foreach}
{include file="footer.tpl"}
