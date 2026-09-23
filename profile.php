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
$smarty->assign('languages', LANGUAGES);
$smarty->assign('password_min', PASSWORD_MIN_LENGTH);
$smarty->display('profile.tpl');
