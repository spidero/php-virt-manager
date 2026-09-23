<?php

// JSON endpoint polled by the pages: machine states for the menu and the
// dashboard, and raw counters of one machine (?node=) for the live charts

require_once 'function.php';

$stats = @libvirt_connect_get_all_domain_stats($con, 0, 0) ?: [];
$domains = [];
foreach (domain_list($con) as $d) {
    $domains[] = ['name' => $d['name'], 'label' => t($d['label']), 'color' => $d['color'], 'state' => $d['id']];
}

$result = ['time' => microtime(true), 'domains' => $domains];
$node = (string)($_GET['node'] ?? '');
if ($node !== '' && isset($stats[$node])) {
    $result['node'] = stats_counters($stats[$node]);
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode($result);
