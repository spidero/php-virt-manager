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

// machine table: texts and percentages for the first render, panel.js updates CPU and memory
$pct = fn($part, $whole) => $whole > 0 ? (int)min(100, round($part / $whole * 100)) : 0;
$vms = [];
foreach (domain_overview($con) as $vm) {
    $vm['mem_pct'] = $pct($vm['mem_used'], $vm['mem_total']);
    $vm['mem_text'] = $vm['active'] && $vm['mem_source'] !== ''
        ? t('%s of %s', format_bytes($vm['mem_used']), format_bytes($vm['mem_total']))
        : format_bytes($vm['mem_total']);
    $vm['disk_pct'] = $pct($vm['disk_alloc'], $vm['disk_cap']);
    $vm['disk_text'] = $vm['disk_cap'] > 0 ? t('%s of %s', format_bytes($vm['disk_alloc']), format_bytes($vm['disk_cap'])) : '';
    $vms[] = $vm;
}
$smarty->assign('vms', $vms);
$smarty->assign('mem_titles', ['guest' => t('used by the guest system (without cache)'), 'host' => t('memory of the QEMU process on the host')]);

$smarty->assign('show_contact', !notice_dismissed(current_user()['id'], 'contact'));
$smarty->assign('repo_url', 'https://github.com/spidero/php-virt-manager');

$smarty->display('index.tpl');
