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
