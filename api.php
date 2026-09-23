<?php

// REST API entry point: api.php/v1/..., see lib/api.php and the README

require_once __DIR__.'/config.php';
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/lib/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
i18n_set_language('en');

function api_respond($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
    exit;
}

$ip = client_ip();
try {
    if ($locked = login_locked_for($ip)) {
        throw new ApiError('too many failed authentications, retry in '.(int)ceil($locked / 60).' min', 429);
    }
    $user = api_token_user(api_bearer_token($_SERVER));
    if (!$user) {
        login_register_failure($ip);
        throw new ApiError('invalid or missing token (Authorization: Bearer pvm_...)', 401);
    }
    $GLOBALS['api_user'] = $user;

    $path = (string)($_SERVER['PATH_INFO'] ?? $_GET['route'] ?? '');
    [$handler, $permission, $args] = api_route((string)$_SERVER['REQUEST_METHOD'], $path);
    if (!role_allows($user['role'], $permission)) {
        throw new ApiError('your role ('.$user['role'].') does not allow this', 403);
    }

    // connection: ?conn=<key>, default the first one
    $conn_key = (string)($_GET['conn'] ?? '');
    if ($conn_key !== '') {
        if (!isset(connections_list()[$conn_key])) {
            throw new ApiError('unknown connection', 404);
        }
        $_SESSION['conn'] = $conn_key;
    }
    $conn = connection_current();
    $con = libvirt_connect($conn['uri'], (bool)$conn['readonly']);
    if (!$con) {
        throw new ApiError('cannot connect to '.$conn['uri'], 502);
    }
    api_respond(200, $handler($con, ...$args));
}
catch (ApiError $e) {
    api_respond($e->getCode() ?: 400, ['error' => $e->getMessage()]);
}
