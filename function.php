<?php

if (!is_file(__DIR__.'/config.php')) {
    http_response_code(500);
    exit('Missing config.php - copy config-default.php to config.php and set credentials.');
}
require_once __DIR__.'/config.php';
require_once __DIR__.'/vendor/autoload.php';

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
    $smarty->assign('domains', libvirt_list_domains($con) ?: []);
}

function redirect($url) {
    header('Location: '.$url);
    exit;
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// stops the request unless it is a POST carrying a valid CSRF token
function csrf_require() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid request (CSRF token mismatch).');
    }
}

// flash message shown once after a redirect; $type is a bootstrap color
function flash_set($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function xpath_return($res, $path) {
    $x = libvirt_domain_xml_xpath($res, $path);
    return $x[0] ?? null;
}

function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1).' '.$units[$i];
}
