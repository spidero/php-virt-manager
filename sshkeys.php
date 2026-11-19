<?php

// personal SSH public keys, offered in the cloud image wizard
require_once 'function.php';
require_permission('operate');

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $error = ssh_key_add($user['id'], (string)($_POST['name'] ?? ''), (string)($_POST['public_key'] ?? ''));
        if ($error) {
            flash_set('danger', $error);
        }
        else {
            action_log('ssh_key_add', $user['username'], true);
            flash_set('success', t('SSH key saved.'));
        }
    }
    elseif ($action === 'delete') {
        if (ssh_key_delete($user['id'], (int)($_POST['id'] ?? 0))) {
            action_log('ssh_key_delete', $user['username'], true);
            flash_set('success', t('SSH key removed.'));
        }
    }
    redirect('sshkeys.php');
}

$smarty->assign('ssh_keys', ssh_key_list($user['id']));
$smarty->assign('ssh_key_name_max', SSH_KEY_NAME_MAX);
$smarty->display('sshkeys.tpl');
