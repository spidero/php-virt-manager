<?php

require_once 'function.php';

$node = (string)($_GET['node'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    http_response_code(404);
    exit;
}
$res = libvirt_domain_lookup_by_name($con, $node);
$shot = libvirt_domain_is_active($res) ? @libvirt_domain_get_screenshot_api($res, 0) : false;
if (!is_array($shot) || !is_file($shot['file'])) {
    http_response_code(404);
    exit;
}

header('Content-Type: '.$shot['mime']);
header('Cache-Control: no-store');
readfile($shot['file']);
unlink($shot['file']);
