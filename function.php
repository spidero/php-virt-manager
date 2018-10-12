<?php

require_once('config.php');
require_once('menu.php');

$smarty->assign('box_info', "");

function xpath_return($res, $path) {
  $x = libvirt_domain_xml_xpath($res, $path);
  return $x[0];
}

?>
