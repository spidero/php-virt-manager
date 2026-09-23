<?php

define('PUBLIC_PAGE', true);
require_once 'function.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$ip = client_ip();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $username = (string)($_POST['user'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    $account = user_find_by_name($username);

    if ((int)db_query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $error = t('No user accounts: set $auth_password_hash in config.php to create the first administrator.');
    }
    elseif ($locked = login_locked_for($ip)) {
        $error = t('Too many failed logins, try again in %d min.', (int)ceil($locked / 60));
    }
    // verify against a dummy hash for unknown users, so response time does not reveal them
    elseif (password_verify($pass, $account['password_hash'] ?? LOGIN_DUMMY_HASH) && $account) {
        login_reset($ip);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['id'];
        unset($_SESSION['csrf']);
        user_update($account['id'], ['last_login' => time()]);
        action_log('login', $account['username'], true, '', $account['username']);
        redirect('index.php');
    }
    else {
        login_register_failure($ip);
        action_log('login', $username, false, 'invalid credentials', $username);
        // slow down brute force attempts
        sleep(1);
        $error = t('Invalid username or password.');
    }
}

$smarty->assign('error', $error);
$smarty->display('login.tpl');
