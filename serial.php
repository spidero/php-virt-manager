<?php

// text console in the browser: xterm.js connected through websockify to the
// machine's serial port on 127.0.0.1 (see vm_serial_xml())
require_once 'function.php';
require_permission('operate');

$node = (string)($_GET['node'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    flash_set('danger', t('Unknown machine: %s', $node));
    redirect('index.php');
}
$node_url = 'node.php?node='.urlencode($node);
$res = libvirt_domain_lookup_by_name($con, $node);

if (!$console_enabled || !connection_is_local($connection)) {
    flash_set('warning', t('Browser console is disabled ($console_enabled in config.php).'));
    redirect($node_url);
}
// the running definition: a console enabled in the persistent one needs a restart
$port = vm_serial_port((string)libvirt_domain_get_xml_desc($res, null, 0));
if (!libvirt_domain_is_active($res) || $port <= 0) {
    flash_set('warning', t('The text console needs a running machine with the text console enabled (Edit).'));
    redirect($node_url);
}

$token = console_token_create('127.0.0.1', $port);
action_log('serial_console', $node, true);

$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$smarty->assign('node', $node);
$smarty->assign('ws_path', '/'.ltrim(($base !== '' ? $base.'/' : '').$console_ws_path, '/').'?token='.$token);
$smarty->assign('key_combos', DOMAIN_KEY_COMBOS);
$smarty->display('serial.tpl');
