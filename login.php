<?php

define('PUBLIC_PAGE', true);
require_once 'function.php';

if (!empty($_SESSION['user'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $user = (string)($_POST['user'] ?? '');
    $pass = (string)($_POST['password'] ?? '');

    if ($auth_password_hash === '') {
        $error = 'Login disabled: set $auth_password_hash in config.php.';
    }
    elseif (hash_equals($auth_user, $user) && password_verify($pass, $auth_password_hash)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        unset($_SESSION['csrf']);
        redirect('index.php');
    }
    else {
        // slow down brute force attempts
        sleep(1);
        $error = 'Invalid username or password.';
    }
}

$smarty->assign('error', $error);
$smarty->display('login.tpl');
