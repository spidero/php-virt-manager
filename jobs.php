<?php

require_once 'function.php';
if (!can('operate') && !can('create')) {
    require_permission('operate');
}

// JSON state of the given jobs for the progress bars: jobs.php?ids=1,2
if (isset($_GET['ids'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $visible = array_column(array_filter(job_list(500), 'job_visible'), 'id');
    echo json_encode(array_intersect_key(job_states(explode(',', (string)$_GET['ids'])), array_flip($visible)));
    exit;
}

$jobs = array_values(array_filter(job_list(100), 'job_visible'));
$smarty->assign('jobs', $jobs);
$smarty->assign('active_jobs', count(array_filter($jobs, fn($job) => in_array($job['status'], ['queued', 'running'], true))));
$smarty->display('jobs.tpl');
