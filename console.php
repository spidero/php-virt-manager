<?php

require_once 'function.php';

$node = (string)($_GET['node'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    flash_set('danger', 'Unknown machine: '.$node);
    redirect('index.php');
}
$node_url = 'node.php?node='.urlencode($node);
$res = libvirt_domain_lookup_by_name($con, $node);
$xml = domain_xml($res);
$graphics = $xml ? domain_graphics($xml) : null;

if (!$console_enabled) {
    flash_set('warning', 'Browser console is disabled ($console_enabled in config.php).');
    redirect($node_url);
}
if (!libvirt_domain_is_active($res) || !$graphics || $graphics['type'] !== 'vnc' || $graphics['port'] <= 0) {
    flash_set('warning', 'Console needs a running machine with VNC graphics.');
    redirect($node_url);
}

$token = console_token_create(console_vnc_host($graphics['listen']), $graphics['port']);
action_log('console', $node, true);

// noVNC builds the websocket URL from the host root, so prefix the panel directory
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$ws_path = ltrim(($base !== '' ? $base.'/' : '').$console_ws_path, '/').'?token='.$token;

$smarty->assign('node', $node);
$smarty->assign('console_url', $console_novnc_url.'?autoconnect=true&scale=true&path='.rawurlencode($ws_path));
$smarty->display('console.tpl');
