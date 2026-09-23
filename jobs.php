<?php

require_once 'function.php';
require_permission('operate');

// JSON state of the given jobs for the progress bars: jobs.php?ids=1,2
if (isset($_GET['ids'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(job_states(explode(',', (string)$_GET['ids'])));
    exit;
}

$jobs = job_list(100);
$smarty->assign('jobs', $jobs);
$smarty->assign('active_jobs', job_active_count());
$smarty->display('jobs.tpl');
