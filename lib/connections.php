<?php

// libvirt connections (hypervisors) defined in config.php; the selected one
// is kept in the session

// normalized list: key => [uri, label, readonly]
function connections_list() {
    global $connections, $connection, $readonly;
    $list = [];
    if (!empty($connections) && is_array($connections)) {
        foreach ($connections as $key => $conn) {
            $list[(string)$key] = [
                'uri'      => (string)$conn['uri'],
                'label'    => (string)($conn['label'] ?? $conn['uri']),
                'readonly' => (int)!empty($conn['readonly']),
            ];
        }
    }
    else {
        // single connection from older config.php files
        $list['default'] = ['uri' => (string)$connection, 'label' => (string)$connection, 'readonly' => (int)$readonly];
    }
    return $list;
}

function connection_current_key() {
    $list = connections_list();
    $key = (string)($_SESSION['conn'] ?? '');
    return isset($list[$key]) ? $key : (string)array_key_first($list);
}

function connection_current() {
    return connections_list()[connection_current_key()];
}

// VNC listens on the hypervisor's localhost, reachable by websockify only for local connections
function connection_is_local($uri) {
    $host = parse_url(str_replace(':///', '://localhost/', $uri), PHP_URL_HOST);
    return in_array($host, [null, '', 'localhost', '127.0.0.1', '::1'], true);
}
