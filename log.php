<?php

require_once 'function.php';

$smarty->assign('entries', action_log_read(500));
$smarty->display('log.tpl');
