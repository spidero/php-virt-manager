<?php

// XML definition editor (administrators): the persistent definition of a machine,
// checked against the libvirt schema and redefined; name and UUID must stay
require_once 'function.php';

$node = (string)($_GET['node'] ?? $_POST['node'] ?? '');
$xml_url = 'xml.php?node='.urlencode($node);
require_permission('admin', 'node.php?node='.urlencode($node));
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    flash_set('danger', t('Unknown machine: %s', $node));
    redirect('index.php');
}
$res = libvirt_domain_lookup_by_name($con, $node);
$uuid = (string)libvirt_domain_get_uuid_string($res);
$current = (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE);
$text = $current;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard($xml_url, 'admin');
    $text = str_replace("\r\n", "\n", (string)($_POST['xml'] ?? ''));
    $errors = vm_xml_identity_errors($text, $node, $uuid);
    if (!$errors && ($schema = vm_xml_schema_error($text)) !== '') {
        $errors[] = t('The XML does not match the libvirt schema:').' '.$schema;
    }
    if (!$errors && !libvirt_domain_define_xml($con, $text)) {
        $errors[] = t('Error: %s', libvirt_get_last_error());
    }
    if (!$errors) {
        action_log('edit_xml', $node, true, strlen($current).' -> '.strlen($text).' bytes');
        $active = (bool)libvirt_domain_is_active($res);
        flash_set('success', t('Definition saved.').($active ? ' '.t('Takes effect after the machine is shut down and started again.') : ''));
        redirect($xml_url);
    }
    action_log('edit_xml', $node, false, implode(' ', $errors));
}

$smarty->assign('node', $node);
$smarty->assign('xml_text', $text);
$smarty->assign('errors', $errors);
$smarty->display('xml.tpl');
