<?php

require_once 'function.php';

// action => [libvirt function, message on success]
$actions = [
    'start'   => ['libvirt_domain_create',   'Starting machine, it may take some time'],
    'stop'    => ['libvirt_domain_shutdown', 'Shutting down machine, it may take some time'],
    'destroy' => ['libvirt_domain_destroy',  'Machine forcibly stopped'],
    'reboot'  => ['libvirt_domain_reboot',   'Rebooting machine, it may take some time'],
    'suspend' => ['libvirt_domain_suspend',  'Suspending machine'],
    'resume'  => ['libvirt_domain_resume',   'Resuming machine'],
];

$node = (string)($_GET['node'] ?? $_POST['node'] ?? '');
$domains = libvirt_list_domains($con) ?: [];
if (!in_array($node, $domains, true)) {
    flash_set('danger', 'Unknown machine: '.$node);
    redirect('index.php');
}
$node_url = 'node.php?node='.urlencode($node);
$res = libvirt_domain_lookup_by_name($con, $node);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $state = (string)($_POST['state'] ?? '');

    if ($readonly) {
        flash_set('danger', 'Connection is read-only.');
    }
    elseif (!isset($actions[$state])) {
        flash_set('danger', 'Unknown action: '.$state);
    }
    else {
        [$function, $message] = $actions[$state];
        if ($function($res)) {
            flash_set('success', $message);
        }
        else {
            flash_set('danger', 'Error: '.libvirt_get_last_error());
        }
    }
    // Post/Redirect/Get - a page refresh must not repeat the action
    redirect($node_url);
}

$smarty->assign('node', $node);
$smarty->assign('domain_uuid', libvirt_domain_get_uuid_string($res));
$smarty->assign('active', libvirt_domain_is_active($res));

$info = libvirt_domain_get_info($res);
$info['maxMem'] = round($info['maxMem']/(1024*1024));
$info['memory'] = round($info['memory']/(1024*1024));
$smarty->assign('info', $info);

if ($info['state'] == 1) {
    $smarty->assign('vnc_ip', xpath_return($res, '//domain/devices/graphics/@listen'));
    $smarty->assign('vnc_port', xpath_return($res, '//domain/devices/graphics/@port'));
}

$smarty->display('node.tpl');
