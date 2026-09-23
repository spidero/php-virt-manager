<?php

require_once 'function.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard('network.php', 'admin');
    $action = (string)($_POST['action'] ?? '');
    $name = (string)($_POST['network'] ?? '');
    $net = in_array($name, libvirt_list_networks($con) ?: [], true) ? libvirt_network_get($con, $name) : null;
    $details = '';
    if (!$net) {
        flash_set('danger', t('Unknown network: %s', $name));
        redirect('network.php');
    }
    if ($action === 'start' || $action === 'stop') {
        $ok = libvirt_network_set_active($net, $action === 'start' ? 1 : 0);
        $message = $action === 'start' ? t('Network %s started.', $name) : t('Network %s stopped.', $name);
    }
    elseif ($action === 'autostart') {
        $enable = !empty($_POST['enable']);
        $ok = libvirt_network_set_autostart($net, $enable ? 1 : 0);
        $details = $enable ? 'on' : 'off';
        $message = $enable ? t('Autostart enabled') : t('Autostart disabled');
    }
    else {
        flash_set('danger', t('Unknown action: %s', $action));
        redirect('network.php');
    }
    $error = $ok ? '' : (string)libvirt_get_last_error();
    action_log('network_'.$action, $name, (bool)$ok, trim($details.' '.$error));
    flash_set($ok ? 'success' : 'danger', $ok ? $message : t('Error: %s', $error));
    redirect('network.php');
}

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
