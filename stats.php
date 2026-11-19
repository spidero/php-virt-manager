<?php

// JSON endpoint polled by the pages: machine states for the menu and the
// dashboard, and raw counters of one machine (?node=) for the live charts

require_once 'function.php';

$stats = @libvirt_connect_get_all_domain_stats($con, 0, 0) ?: [];
// only the machines the user may see (own-machine roles)
$visible = machine_filter_visible(array_column(domain_list($con), 'name'));
$stats = array_intersect_key($stats, array_flip($visible));
$domains = [];
foreach (array_filter(domain_list($con), fn($d) => in_array($d['name'], $visible, true)) as $d) {
    $domains[] = ['name' => $d['name'], 'label' => t($d['label']), 'color' => $d['color'], 'state' => $d['id']];
}

$result = ['time' => microtime(true), 'domains' => $domains];
// host load for the charts of the hypervisor page (the CPU call takes about a second)
if (!empty($_GET['host']) && can('view')) {
    $memory = node_memory(libvirt_node_get_mem_stats($con) ?: []);
    $result['host'] = [
        'cpu'       => node_cpu_percent(@libvirt_node_get_cpu_stats($con)),
        'mem_used'  => $memory['used'],
        'mem_total' => $memory['total'],
    ];
}
// counters of all machines for the machine table of the hypervisor page
if (!empty($_GET['all'])) {
    $result['vms'] = [];
    foreach ($stats as $name => $raw) {
        $result['vms'][$name] = stats_counters($raw);
    }
}
$node = (string)($_GET['node'] ?? '');
if ($node !== '' && isset($stats[$node])) {
    $result['node'] = stats_counters($stats[$node]);
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode($result);
