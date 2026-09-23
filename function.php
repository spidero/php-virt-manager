<?php

if (!is_file(__DIR__.'/config.php')) {
    http_response_code(500);
    exit('Missing config.php - copy config-default.php to config.php and set credentials.');
}
require_once __DIR__.'/config.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/lib/common.php';
require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/actionlog.php';
require_once __DIR__.'/lib/domain.php';
require_once __DIR__.'/lib/console.php';

// defaults for settings missing in older config.php files
$data_dir           = $data_dir ?? __DIR__.'/data';
$login_max_attempts = $login_max_attempts ?? 5;
$login_lock_seconds = $login_lock_seconds ?? 900;
$console_enabled    = $console_enabled ?? false;
$console_novnc_url  = $console_novnc_url ?? 'novnc/vnc_lite.html';
$console_ws_path    = $console_ws_path ?? 'websockify';
$smarty_caching     = $smarty_caching ?? 0;

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

$smarty = new Smarty();
$smarty->setTemplateDir(__DIR__.'/templates');
$smarty->setCompileDir(__DIR__.'/templates_c');
$smarty->setCacheDir(__DIR__.'/cache');
$smarty->setConfigDir(__DIR__.'/configs');
$smarty->caching = $smarty_caching;
$smarty->error_reporting = E_ALL & ~E_NOTICE;
// escape every template variable by default (XSS protection)
$smarty->escape_html = true;

$smarty->assign('connection', $connection);
$smarty->assign('readonly', $readonly);
$smarty->assign('csrf_token', csrf_token());
$smarty->assign('logged_user', $_SESSION['user'] ?? null);
$smarty->assign('flash', flash_get());
$smarty->assign('page', basename($_SERVER['SCRIPT_NAME'], '.php'));

// every page except login.php requires an authenticated session
if (!defined('PUBLIC_PAGE') && empty($_SESSION['user'])) {
    redirect('login.php');
}

if (!defined('PUBLIC_PAGE')) {
    $con = libvirt_connect($connection, (bool)$readonly);
    if (!$con) {
        http_response_code(500);
        exit('Cannot connect to '.htmlspecialchars($connection).': '.htmlspecialchars((string)libvirt_get_last_error()));
    }
    $smarty->assign('domains', domain_list($con));
}

// stops a state-changing request when the connection is read-only
function readonly_guard($back_url) {
    global $readonly;
    if ($readonly) {
        flash_set('danger', 'Connection is read-only.');
        redirect($back_url);
    }
}
