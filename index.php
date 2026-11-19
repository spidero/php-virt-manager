<?php

require_once 'function.php';

$get_hypervisor = libvirt_connect_get_hypervisor($con);
$smarty->assign('get_hypervisor', $get_hypervisor);

$node_info = libvirt_node_get_info($con);
$smarty->assign('node_info', $node_info);
$memory = node_memory(libvirt_node_get_mem_stats($con) ?: []);
foreach (['used', 'total', 'available'] as $key) {
    $memory[$key.'_text'] = format_bytes($memory[$key]);
}
$smarty->assign('memory', $memory);
$smarty->assign('cpu_model', node_cpu_model(libvirt_connect_get_sysinfo($con), libvirt_connect_get_capabilities($con)));

// number of machines per state label for the summary tiles
$state_counts = [];
foreach (domain_list($con) as $d) {
    $state_counts[$d['label']] = ($state_counts[$d['label']] ?? 0) + 1;
}
$smarty->assign('state_counts', $state_counts);

$smarty->display('index.tpl');
