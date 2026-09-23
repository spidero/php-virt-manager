<?php

require_once 'function.php';

$networks = [];
foreach (libvirt_list_networks($con) ?: [] as $name) {
    $net = libvirt_network_get($con, $name);
    if (!$net) {
        continue;
    }
    $info = libvirt_network_get_information($net) ?: [];
    $active = (bool)libvirt_network_get_active($net);
    $leases = [];
    if ($active) {
        foreach (@libvirt_network_get_dhcp_leases($net) ?: [] as $lease) {
            $leases[] = [
                'ip'       => ($lease['ipaddr'] ?? '').'/'.($lease['prefix'] ?? ''),
                'mac'      => $lease['mac'] ?? '',
                'hostname' => $lease['hostname'] ?? '',
                'expires'  => isset($lease['expirytime']) ? date('Y-m-d H:i', (int)$lease['expirytime']) : '',
            ];
        }
    }
    $networks[] = [
        'name'       => $name,
        'active'     => $active,
        'autostart'  => (bool)libvirt_network_get_autostart($net),
        'bridge'     => (string)@libvirt_network_get_bridge($net),
        'forwarding' => $info['forwarding'] ?? '',
        'ip_range'   => $info['ip_range'] ?? '',
        'gateway'    => $info['ip'] ?? '',
        'dhcp'       => isset($info['dhcp_start']) ? $info['dhcp_start'].' - '.$info['dhcp_end'] : '',
        'leases'     => $leases,
    ];
}

$smarty->assign('networks', $networks);
$smarty->display('network.tpl');
