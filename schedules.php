<?php

require_once 'function.php';
require_permission('admin');

$smarty->assign('schedules', schedule_list());
$smarty->display('schedules.tpl');
