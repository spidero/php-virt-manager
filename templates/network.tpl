{include file="header.tpl"}
{foreach from=$networks item=n}
<div class="card mb-3">
  <div class="card-header">
    <b>{$n.name}</b>
    <span class="badge badge-{if $n.active}success{else}danger{/if}">{if $n.active}active{else}inactive{/if}</span>
    {if $n.autostart}<span class="badge badge-info">autostart</span>{/if}
  </div>
  <div class="card-body">
    <table class="table table-sm">
      <tr><th style="width: 30%">Bridge</th><td>{$n.bridge|default:'-'}</td></tr>
      <tr><th>Forwarding</th><td>{$n.forwarding|default:'-'}</td></tr>
      <tr><th>Network</th><td>{$n.ip_range|default:'-'}</td></tr>
      <tr><th>Gateway</th><td>{$n.gateway|default:'-'}</td></tr>
      <tr><th>DHCP range</th><td>{$n.dhcp|default:'-'}</td></tr>
    </table>
    <h6>DHCP leases</h6>
    <table class="table table-sm mb-0">
      <thead><tr><th>IP</th><th>MAC</th><th>Hostname</th><th>Expires</th></tr></thead>
      <tbody>
{foreach from=$n.leases item=l}
        <tr><td>{$l.ip}</td><td><code>{$l.mac}</code></td><td>{$l.hostname|default:'-'}</td><td>{$l.expires}</td></tr>
{foreachelse}
        <tr><td colspan="4">No leases</td></tr>
{/foreach}
      </tbody>
    </table>
  </div>
</div>
{foreachelse}
<div class="card"><div class="card-body">No networks found</div></div>
{/foreach}
{include file="footer.tpl"}
