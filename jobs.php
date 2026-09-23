<?php

require_once 'function.php';
require_permission('operate');

$jobs = job_list(100);
$smarty->assign('jobs', $jobs);
$smarty->assign('active_jobs', job_active_count());
$smarty->display('jobs.tpl');
