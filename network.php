<?php

require_once 'function.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard('network.php', 'admin');
    $action = (string)($_POST['action'] ?? '');
    $name = (string)($_POST['network'] ?? '');
    $details = '';
    $message = '';
    $error_text = '';
    $all_networks = libvirt_list_networks($con) ?: [];

    if ($action === 'create') {
        $mode = (string)($_POST['mode'] ?? 'nat');
        $cidr = network_cidr((string)($_POST['subnet'] ?? ''));
        $error = '';
        if (!preg_match(DOMAIN_NAME_PATTERN, $name)) {
            $error = t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).');
        }
        elseif (in_array($name, $all_networks, true)) {
            $error = t('A network with this name already exists.');
        }
        elseif (!isset(NETWORK_MODES[$mode])) {
            $error = t('Select the network type.');
        }
        elseif (!$cidr) {
            $error = t('Subnet: an IPv4 network with a prefix /8 to /29, e.g. 192.168.50.0/24.');
        }
        else {
            foreach ($all_networks as $other) {
                $other_net = libvirt_network_get($con, $other);
                $other_ip = $other_net ? network_ipv4(simplexml_load_string((string)libvirt_network_get_xml_desc($other_net))) : null;
                if ($other_ip && network_overlaps($other_ip['cidr'], $cidr['network'].'/'.$cidr['prefix'])) {
                    $error = t('The subnet overlaps network %s (%s).', $other, $other_ip['cidr']);
                }
            }
        }
        $net = $error === '' ? libvirt_network_define_xml($con, network_new_xml($name, $mode, $cidr, !empty($_POST['dhcp']))) : false;
        if ($error === '' && !$net) {
            $error = t('Error: %s', libvirt_get_last_error());
        }
        if ($net) {
            if (!empty($_POST['autostart'])) {
                libvirt_network_set_autostart($net, 1);
            }
            if (!libvirt_network_set_active($net, 1)) {
                $error = t('The network was created but could not be started: %s', libvirt_get_last_error());
            }
        }
        $details = $mode.' '.($cidr ? $cidr['network'].'/'.$cidr['prefix'] : '');
        action_log('network_create', $name, $error === '', trim($details.' '.$error));
        flash_set($error === '' ? 'success' : 'danger', $error === '' ? t('Network %s created.', $name) : $error);
        redirect('network.php');
    }

    $net = in_array($name, $all_networks, true) ? libvirt_network_get($con, $name) : null;
    if (!$net) {
        flash_set('danger', t('Unknown network: %s', $name));
        redirect('network.php');
    }
    $active = (bool)libvirt_network_get_active($net);
    $ipv4 = network_ipv4(simplexml_load_string((string)libvirt_network_get_xml_desc($net)));
    $cidr = $ipv4 ? network_cidr($ipv4['cidr']) : null;

    if ($action === 'delete') {
        $users = array_unique(array_values(network_machine_macs($con, $name)));
        $ok = false;
        if ($users) {
            $error_text = t('The network is used by: %s. Connect these machines to another network first.', implode(', ', $users));
        }
        else {
            $ok = (!$active || libvirt_network_set_active($net, 0)) && libvirt_network_undefine($net);
            $message = t('Network %s deleted.', $name);
        }
    }
    elseif (in_array($action, ['dhcp_range', 'host_add', 'host_delete'], true)) {
        if (!$cidr) {
            $error_text = t('The network has no IPv4 address.');
        }
        elseif ($action === 'dhcp_range') {
            $start = trim((string)($_POST['start'] ?? ''));
            $end = trim((string)($_POST['end'] ?? ''));
            if (!network_ip_usable($start, $cidr) || !network_ip_usable($end, $cidr) || ip2long($start) > ip2long($end)) {
                $error_text = t('The DHCP range must be inside %s, start before end.', $ipv4['cidr']);
            }
            else {
                // a range cannot be modified in place: remove the old one, add the new one
                if ($ipv4['range']) {
                    $error_text = network_update($connection, $name, 'delete', 'ip-dhcp-range',
                        "<range start='".$ipv4['range']['start']."' end='".$ipv4['range']['end']."'/>", $active);
                }
                if ($error_text === '') {
                    $error_text = network_update($connection, $name, 'add', 'ip-dhcp-range', "<range start='$start' end='$end'/>", $active);
                }
                $details = $start.' - '.$end;
                $message = t('DHCP range of %s changed.', $name);
            }
        }
        elseif ($action === 'host_add') {
            $mac = strtolower(trim((string)($_POST['mac'] ?? '')));
            $ip = trim((string)($_POST['ip'] ?? ''));
            $host = trim((string)($_POST['hostname'] ?? ''));
            if (!preg_match(NETWORK_MAC_PATTERN, $mac)) {
                $error_text = t('Invalid MAC address.');
            }
            elseif (!network_ip_usable($ip, $cidr)) {
                $error_text = t('The address must be inside %s.', $ipv4['cidr']);
            }
            elseif ($host !== '' && !preg_match(NETWORK_HOSTNAME_PATTERN, $host)) {
                $error_text = t('Invalid host name.');
            }
            else {
                foreach ($ipv4['hosts'] as $h) {
                    if ($h['mac'] === $mac || $h['ip'] === $ip) {
                        $error_text = t('The MAC or IP address is already assigned (%s - %s).', $h['mac'], $h['ip']);
                    }
                }
            }
            if ($error_text === '') {
                $error_text = network_update($connection, $name, 'add', 'ip-dhcp-host',
                    "<host mac='$mac'".($host !== '' ? " name='".xml_escape($host)."'" : '')." ip='$ip'/>", $active);
                $details = $mac.' '.$ip.' '.$host;
                $message = t('%s gets %s (from the next DHCP request of the machine).', $mac, $ip);
            }
        }
        else {
            $mac = strtolower(trim((string)($_POST['mac'] ?? '')));
            $error_text = preg_match(NETWORK_MAC_PATTERN, $mac)
                ? network_update($connection, $name, 'delete', 'ip-dhcp-host', "<host mac='$mac'/>", $active)
                : t('Invalid MAC address.');
            $details = $mac;
            $message = t('Static address of %s removed.', $mac);
        }
        $ok = $error_text === '';
    }
    elseif ($action === 'start' || $action === 'stop') {
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
    $error = $ok ? '' : ($error_text !== '' ? $error_text : (string)libvirt_get_last_error());
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
                'ip'       => (string)($lease['ipaddr'] ?? ''),
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
        'ipv4'       => network_ipv4(simplexml_load_string((string)libvirt_network_get_xml_desc($net))),
        'machines'   => network_machine_macs($con, $name),
    ];
}

$smarty->assign('networks', $networks);
$smarty->assign('network_modes', NETWORK_MODES);
$smarty->display('network.tpl');
