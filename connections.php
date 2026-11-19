<?php

// hypervisor connections: those of config.php (read-only here) and those added by administrators
require_once 'function.php';
require_permission('admin');

$test_results = $_SESSION['connection_tests'] ?? [];
unset($_SESSION['connection_tests']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');
    $key = (string)($_POST['key'] ?? '');
    $list = connections_list();
    if ($action === 'add') {
        $errors = connection_add((string)($_POST['label'] ?? ''), (string)($_POST['uri'] ?? ''), !empty($_POST['readonly']));
        action_log('connection_add', (string)($_POST['uri'] ?? ''), !$errors, implode(' ', $errors));
        flash_set($errors ? 'danger' : 'success', $errors ? implode(' ', $errors) : t('Connection added - switch to it in the menu.'));
    }
    elseif ($action === 'delete' && isset($list[$key]) && $list[$key]['source'] === 'panel') {
        connection_delete($key);
        action_log('connection_delete', $list[$key]['uri'], true);
        flash_set('success', t('Connection %s removed.', $list[$key]['label']));
    }
    elseif ($action === 'test' && isset($list[$key])) {
        $error = connection_test($list[$key]['uri']);
        $_SESSION['connection_tests'] = [$key => $error === '' ? ['ok' => true, 'text' => t('Connected.')] : ['ok' => false, 'text' => $error]];
    }
    redirect('connections.php');
}

$smarty->assign('connection_list', connections_list());
$smarty->assign('test_results', $test_results);
$smarty->display('connections.tpl');
