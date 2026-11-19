<?php

require_once 'function.php';

$node = (string)($_GET['node'] ?? '');
if (!machine_visible($con, $node)) {
    flash_set('danger', t('Unknown machine: %s', $node));
    redirect('index.php');
}
$node_url = 'node.php?node='.urlencode($node);
require_machine('operate', $node, $node_url);
$res = libvirt_domain_lookup_by_name($con, $node);
$xml = domain_xml($res);
$graphics = $xml ? domain_graphics($xml) : null;

if (!$console_enabled || !connection_is_local($connection)) {
    flash_set('warning', t('Browser console is disabled ($console_enabled in config.php).'));
    redirect($node_url);
}
if (!libvirt_domain_is_active($res) || !$graphics || $graphics['type'] !== 'vnc' || $graphics['port'] <= 0) {
    flash_set('warning', t('Console needs a running machine with VNC graphics.'));
    redirect($node_url);
}

$token = console_token_create(console_vnc_host($graphics['listen']), $graphics['port']);
action_log('console', $node, true);

// noVNC builds the websocket URL from the host root, so prefix the panel directory
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$ws_path = ltrim(($base !== '' ? $base.'/' : '').$console_ws_path, '/').'?token='.$token;

$smarty->assign('node', $node);
$smarty->assign('key_combos', DOMAIN_KEY_COMBOS);
// a VNC password (only visible with the "secure" XML flag) is passed to noVNC
$secure = @simplexml_load_string((string)libvirt_domain_get_xml_desc($res, null, 1));
$vnc_password = $secure ? (string)($secure->xpath("/domain/devices/graphics[@type='vnc']")[0]['passwd'] ?? '') : '';
$smarty->assign('console_url', $console_novnc_url.'?autoconnect=true&scale=true&path='.rawurlencode($ws_path)
    .($vnc_password !== '' ? '&password='.rawurlencode($vnc_password) : ''));
$smarty->display('console.tpl');
