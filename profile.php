<?php

require_once 'function.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'lang') {
        $lang = (string)($_POST['lang'] ?? '');
        if (isset(LANGUAGES[$lang])) {
            user_update($user['id'], ['lang' => $lang]);
            i18n_set_language($lang);
            flash_set('success', t('Language changed.'));
        }
    }
    elseif ($action === 'token_create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            flash_set('danger', t('Enter a token name (up to 64 characters).'));
        }
        else {
            // shown once on the next page load, only the hash is stored
            $_SESSION['new_token'] = api_token_create($user['id'], $name);
            action_log('api_token_create', $user['username'], true, $name);
            flash_set('success', t('Token created. Copy it now, it will not be shown again.'));
        }
    }
    elseif ($action === 'token_delete') {
        if (api_token_delete($user['id'], (int)($_POST['id'] ?? 0))) {
            action_log('api_token_delete', $user['username'], true);
            flash_set('success', t('Token revoked.'));
        }
    }
    elseif ($action === 'password') {
        $current = (string)($_POST['current'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if (!password_verify($current, $user['password_hash'])) {
            flash_set('danger', t('Current password is incorrect.'));
        }
        elseif ($password !== (string)($_POST['password2'] ?? '')) {
            flash_set('danger', t('Passwords do not match.'));
        }
        elseif ($error = user_validate_password($password)) {
            flash_set('danger', $error);
        }
        else {
            user_update($user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            action_log('password_change', $user['username'], true);
            flash_set('success', t('Password changed.'));
        }
    }
    redirect('profile.php');
}

$smarty->assign('user', $user);
$smarty->assign('tokens', api_token_list($user['id']));
$smarty->assign('new_token', $_SESSION['new_token'] ?? null);
unset($_SESSION['new_token']);
$smarty->assign('api_url', (!empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost')
    .rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\').'/api.php/v1');
$smarty->assign('languages', LANGUAGES);
$smarty->assign('password_min', PASSWORD_MIN_LENGTH);
$smarty->display('profile.tpl');
