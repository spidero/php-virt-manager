<?php

session_start();
require_once 'vendor/autoload.php';

require_once('vendor/smarty/smarty/libs/Smarty.class.php');
$smarty = new Smarty();

// enable/disable smarty caching
$smarty->caching = 0;

// set smarty reporting level
$smarty->error_reporting = E_ALL & ~E_NOTICE;

#local conenction to qemu
$connection = 'qemu:///system';
$smarty->assign('connection', $connection);

//connection in readonly 0-no, 1-yes
$readonly = 0;
$smarty->assign('readonly', $readonly);

$con =  libvirt_connect($connection, $readonly);
?>
