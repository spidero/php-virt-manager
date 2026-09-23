<?php

require_once 'function.php';
require_permission('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $target = $id ? user_find($id) : null;
    $self = current_user();

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        if (!preg_match(USERNAME_PATTERN, $username)) {
            flash_set('danger', t('Invalid username (letters, digits, . _ @ - only).'));
        }
        elseif (user_find_by_name($username)) {
            flash_set('danger', t('User %s already exists.', $username));
        }
        elseif (!isset(ROLES[$role])) {
            flash_set('danger', t('Unknown role.'));
        }
        elseif ($error = user_validate_password($password)) {
            flash_set('danger', $error);
        }
        else {
            user_create($username, password_hash($password, PASSWORD_DEFAULT), $role);
            action_log('user_create', $username, true, $role);
            flash_set('success', t('User %s created.', $username));
        }
    }
    elseif (!$target) {
        flash_set('danger', t('Unknown user.'));
    }
    elseif ($action === 'role') {
        $role = (string)($_POST['role'] ?? '');
        if (!isset(ROLES[$role])) {
            flash_set('danger', t('Unknown role.'));
        }
        elseif ($target['role'] === 'admin' && $role !== 'admin' && user_count_admins() <= 1) {
            flash_set('danger', t('At least one administrator is required.'));
        }
        else {
            user_update($target['id'], ['role' => $role]);
            action_log('user_role', $target['username'], true, $role);
            flash_set('success', t('Role of %s changed to %s.', $target['username'], t($role)));
        }
    }
    elseif ($action === 'password') {
        $password = (string)($_POST['password'] ?? '');
        if ($error = user_validate_password($password)) {
            flash_set('danger', $error);
        }
        else {
            user_update($target['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            action_log('user_password', $target['username'], true);
            flash_set('success', t('Password of %s changed.', $target['username']));
        }
    }
    elseif ($action === 'delete') {
        if ((int)$target['id'] === (int)$self['id']) {
            flash_set('danger', t('You cannot delete your own account.'));
        }
        elseif ($target['role'] === 'admin' && user_count_admins() <= 1) {
            flash_set('danger', t('At least one administrator is required.'));
        }
        else {
            user_delete($target['id']);
            action_log('user_delete', $target['username'], true);
            flash_set('success', t('User %s deleted.', $target['username']));
        }
    }
    redirect('users.php');
}

$smarty->assign('users', user_list());
$smarty->assign('roles', array_keys(ROLES));
$smarty->assign('self_id', (int)current_user()['id']);
$smarty->assign('password_min', PASSWORD_MIN_LENGTH);
$smarty->display('users.tpl');
