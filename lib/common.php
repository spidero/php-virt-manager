<?php

function redirect($url): never {
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        exit('Invalid request (CSRF token mismatch).');
    }
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        // usually a form left open while the session expired (or the server
        // restarted): send the user back to the form with a hint instead of an error
        session_expired_redirect(current_user() ? ($_SERVER['REQUEST_URI'] ?? 'index.php') : 'login.php');
    }
}

function session_expired_redirect($url): never {
    flash_set('warning', t('Your session has expired, please try again.'));
    redirect($url);
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

function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1).' '.$units[$i];
}

function client_ip() {
    return (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

// absolute path inside the data directory, creating parent dirs when needed
function data_path($name) {
    global $data_dir;
    $path = rtrim($data_dir, '/').'/'.$name;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    return $path;
}

function xml_escape($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// reads and rewrites a JSON file under an exclusive lock
function json_file_update($path, callable $callback) {
    $fh = fopen($path, 'c+');
    if (!$fh) {
        return null;
    }
    flock($fh, LOCK_EX);
    $data = json_decode((string)stream_get_contents($fh), true) ?: [];
    $data = $callback($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string)json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}

// time zone of the operating system: $TZ, /etc/timezone or the /etc/localtime link
// (php.ini often says UTC while the host runs in local time)
function system_timezone() {
    $candidates = [(string)getenv('TZ'), trim((string)@file_get_contents('/etc/timezone'))];
    $link = @readlink('/etc/localtime');
    if ($link && preg_match('#zoneinfo/(.+)$#', $link, $m)) {
        $candidates[] = $m[1];
    }
    foreach ($candidates as $tz) {
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }
    }
    return date_default_timezone_get();
}

const APP_REPO_URL = 'https://github.com/spidero/php-virt-manager';

// version of the panel: the VERSION file ("<version> <revision>", written by the Docker
// build and deploy/install.sh), otherwise the commit of a git checkout, otherwise "dev"
function app_version($root = null) {
    static $cache = [];
    $root = $root ?? dirname(__DIR__);
    if (isset($cache[$root])) {
        return $cache[$root];
    }
    $version = 'dev';
    $revision = '';
    $file = @file_get_contents($root.'/VERSION');
    if ($file !== false && preg_match('/^(\S+)(?:\s+([0-9a-f]{7,40}|unknown))?/', trim($file), $m)) {
        $version = $m[1];
        $revision = ($m[2] ?? '') === 'unknown' ? '' : ($m[2] ?? '');
    }
    elseif (is_file($root.'/.git/HEAD')) {
        // read the commit without running git
        $head = trim((string)@file_get_contents($root.'/.git/HEAD'));
        if (preg_match('/^ref: (refs\/\S+)$/', $head, $m)) {
            $ref = @file_get_contents($root.'/.git/'.$m[1]);
            if ($ref === false && preg_match('/^([0-9a-f]{40}) '.preg_quote($m[1], '/').'$/m', (string)@file_get_contents($root.'/.git/packed-refs'), $p)) {
                $ref = $p[1];
            }
            $head = trim((string)$ref);
        }
        $revision = preg_match('/^[0-9a-f]{40}$/', $head) ? $head : '';
    }
    $release = preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    return $cache[$root] = [
        'version'      => $version,
        'revision'     => substr($revision, 0, 7),
        'version_url'  => $release ? APP_REPO_URL.'/releases/tag/v'.$version : APP_REPO_URL,
        'revision_url' => $revision !== '' ? APP_REPO_URL.'/commit/'.$revision : '',
    ];
}
