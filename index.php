<?php

require_once ('function.php');

$get_hypervisor = libvirt_connect_get_hypervisor($con);
$smarty->assign('get_hypervisor', $get_hypervisor);

$node_info = libvirt_node_get_info($con);
$node_info['memory'] = round($node_info['memory']/(1024*1024)); 
$smarty->assign('node_info', $node_info);

$smarty_name =  basename(__FILE__, '.php').'.tpl';
$smarty->display($smarty_name);
?>
