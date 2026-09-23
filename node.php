<?php

require_once 'function.php';

// power action => [libvirt function, message on success]
$power_actions = [
    'start'   => ['libvirt_domain_create',   'Starting machine, it may take some time'],
    'stop'    => ['libvirt_domain_shutdown', 'Shutting down machine, it may take some time'],
    'destroy' => ['libvirt_domain_destroy',  'Machine forcibly stopped'],
    'reboot'  => ['libvirt_domain_reboot',   'Rebooting machine, it may take some time'],
    'suspend' => ['libvirt_domain_suspend',  'Suspending machine'],
    'resume'  => ['libvirt_domain_resume',   'Resuming machine'],
];

$node = (string)($_GET['node'] ?? $_POST['node'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    flash_set('danger', 'Unknown machine: '.$node);
    redirect('index.php');
}
$node_url = 'node.php?node='.urlencode($node);
$res = libvirt_domain_lookup_by_name($con, $node);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard($node_url);
    $action = (string)($_POST['action'] ?? '');
    $ok = false;
    $details = '';
    $message = '';

    if (isset($power_actions[$action])) {
        [$function, $message] = $power_actions[$action];
        $ok = (bool)$function($res);
    }
    elseif ($action === 'autostart') {
        $enable = !empty($_POST['enable']);
        $details = $enable ? 'on' : 'off';
        $ok = (bool)libvirt_domain_set_autostart($res, $enable);
        $message = 'Autostart '.$details;
    }
    elseif ($action === 'snapshot_create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $name = date('Y-m-d_H-i-s');
        }
        $details = $name;
        if (!preg_match(DOMAIN_NAME_PATTERN, $name)) {
            flash_set('danger', 'Invalid snapshot name (letters, digits, . _ - only).');
            redirect($node_url);
        }
        $xml = domain_snapshot_xml($name, (string)($_POST['description'] ?? ''));
        $ok = (bool)libvirt_domain_snapshot_create_xml($res, $xml);
        $message = 'Snapshot '.$name.' created';
    }
    elseif ($action === 'snapshot_revert' || $action === 'snapshot_delete') {
        $name = (string)($_POST['snapshot'] ?? '');
        $details = $name;
        if (!in_array($name, libvirt_list_domain_snapshots($res) ?: [], true)) {
            flash_set('danger', 'Unknown snapshot: '.$name);
            redirect($node_url);
        }
        $snap = libvirt_domain_snapshot_lookup_by_name($res, $name);
        if ($action === 'snapshot_revert') {
            $ok = (bool)libvirt_domain_snapshot_revert($snap);
            $message = 'Reverted to snapshot '.$name;
        }
        else {
            $ok = (bool)libvirt_domain_snapshot_delete($snap);
            $message = 'Snapshot '.$name.' deleted';
        }
    }
    elseif ($action === 'switch_vnc') {
        $new_xml = domain_xml_spice_to_vnc((string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE));
        if ($new_xml === null) {
            flash_set('danger', 'Machine has no SPICE graphics to switch.');
            redirect($node_url);
        }
        $ok = (bool)libvirt_domain_define_xml($con, $new_xml);
        $message = libvirt_domain_is_active($res)
            ? 'Graphics switched to VNC - takes effect after the machine is shut down and started again'
            : 'Graphics switched to VNC';
    }
    else {
        flash_set('danger', 'Unknown action: '.$action);
        redirect($node_url);
    }

    $error = $ok ? '' : (string)libvirt_get_last_error();
    action_log($action, $node, $ok, trim($details.' '.$error));
    flash_set($ok ? 'success' : 'danger', $ok ? $message : 'Error: '.$error);
    // Post/Redirect/Get - a page refresh must not repeat the action
    redirect($node_url);
}

$info = libvirt_domain_get_info($res);
$info['maxMem'] = round($info['maxMem']/(1024*1024), 1);
$info['memory'] = round($info['memory']/(1024*1024), 1);
$active = (bool)libvirt_domain_is_active($res);
$xml = domain_xml($res);

$smarty->assign('node', $node);
$smarty->assign('domain_uuid', libvirt_domain_get_uuid_string($res));
$smarty->assign('active', $active);
$smarty->assign('info', $info);
$smarty->assign('state', domain_state($info['state']));
$smarty->assign('autostart', (bool)libvirt_domain_get_autostart($res));
$smarty->assign('disks', $xml ? domain_disks($res, $xml) : []);
$smarty->assign('interfaces', $xml ? domain_interfaces($res, $xml, $active) : []);
$smarty->assign('graphics', $xml ? domain_graphics($xml) : null);
$smarty->assign('snapshots', domain_snapshots($res));
// spice in the persistent definition decides whether the switch button is shown
$inactive_xml = domain_xml($res, true);
$smarty->assign('persistent_graphics', $inactive_xml ? domain_graphics($inactive_xml) : null);
$smarty->assign('console_enabled', $console_enabled);

$smarty->display('node.tpl');
