<?php

require_once 'function.php';

$get_hypervisor = libvirt_connect_get_hypervisor($con);
$smarty->assign('get_hypervisor', $get_hypervisor);

$node_info = libvirt_node_get_info($con);
$node_info['memory'] = round($node_info['memory']/(1024*1024));
$smarty->assign('node_info', $node_info);

// number of machines per state label for the summary tiles
$state_counts = [];
foreach (domain_list($con) as $d) {
    $state_counts[$d['label']] = ($state_counts[$d['label']] ?? 0) + 1;
}
$smarty->assign('state_counts', $state_counts);

$smarty->display('index.tpl');
