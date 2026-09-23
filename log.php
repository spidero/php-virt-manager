<?php

require_once 'function.php';
require_permission('admin');

$smarty->assign('entries', action_log_read(500));
$smarty->display('log.tpl');
