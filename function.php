<?php

if (!is_file(__DIR__.'/config.php')) {
    http_response_code(500);
    exit('Missing config.php - copy config-default.php to config.php and set credentials.');
}
require_once __DIR__.'/config.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/lib/bootstrap.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

users_bootstrap($auth_user, $auth_password_hash);

// language: user profile, then browser preference
$user = current_user();
i18n_set_language($user['lang'] ?? i18n_detect($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));

// selected hypervisor; ?conn= switches it
if (isset($_GET['conn']) && isset(connections_list()[(string)$_GET['conn']])) {
    $_SESSION['conn'] = (string)$_GET['conn'];
}
$current_connection = connection_current();
$connection = $current_connection['uri'];
$readonly = $current_connection['readonly'];

$smarty = new \Smarty\Smarty();
$smarty->setTemplateDir(__DIR__.'/templates');
$smarty->setCompileDir(__DIR__.'/templates_c');
$smarty->setCacheDir(__DIR__.'/cache');
$smarty->setConfigDir(__DIR__.'/configs');
$smarty->caching = $smarty_caching;
$smarty->error_reporting = E_ALL & ~E_NOTICE;
// escape every template variable by default (XSS protection)
$smarty->escape_html = true;
// {'Text %s'|t:$arg} translates a string
$smarty->registerPlugin('modifier', 't', 't');

$smarty->assign('connection', $connection);
$smarty->assign('connections', connections_list());
$smarty->assign('connection_key', connection_current_key());
$smarty->assign('readonly', $readonly);
$smarty->assign('csrf_token', csrf_token());
$smarty->assign('logged_user', $user['username'] ?? null);
$smarty->assign('user_role', $user['role'] ?? null);
$smarty->assign('can', permission_flags());
// what the user may change on this connection (role and read-only mode combined)
$smarty->assign('allow', ['operate' => can('operate') && !$readonly, 'admin' => can('admin') && !$readonly]);
$smarty->assign('lang', i18n_language());
$smarty->assign('flash', flash_get());
$smarty->assign('page', basename($_SERVER['SCRIPT_NAME'], '.php'));
$smarty->assign('weekdays', ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);

// every page except login.php requires an authenticated session
if (!defined('PUBLIC_PAGE') && $user === null) {
    unset($_SESSION['user_id']);
    redirect('login.php');
}

if (!defined('PUBLIC_PAGE')) {
    $con = libvirt_connect($connection, (bool)$readonly);
    if (!$con) {
        http_response_code(500);
        exit('Cannot connect to '.htmlspecialchars($connection).': '.htmlspecialchars((string)libvirt_get_last_error()));
    }
    $smarty->assign('domains', domain_list($con));

    // background tasks are queued but bin/cron.php does not run (e.g. development server)
    $cron_pages = ['jobs', 'cloud', 'schedules'];
    $smarty->assign('cron_warning', can('operate') && cron_stale()
        && (in_array(basename($_SERVER['SCRIPT_NAME'], '.php'), $cron_pages, true) || job_active_count() > 0));
    $smarty->assign('cron_last_run', cron_last_run());
}

// stops a state-changing request when the connection is read-only
// or the user lacks the permission
function readonly_guard($back_url, $permission = 'operate') {
    global $readonly;
    require_permission($permission, $back_url);
    if ($readonly) {
        flash_set('danger', t('Connection is read-only.'));
        redirect($back_url);
    }
}
