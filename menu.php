<?php

$domains = libvirt_list_domains($con);
$smarty->assign('domains', $domains);

?>
