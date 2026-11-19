<?php

// libvirt connections (hypervisors): defined in config.php (source "config") and
// added by administrators in the panel (source "panel", database); the selected one
// is kept in the session

// accepted connection URIs: qemu, qemu+ssh/tls/tcp/libssh2, system or session
const CONNECTION_URI_PATTERN = '#^qemu(\+(ssh|tls|tcp|libssh2))?://([A-Za-z0-9._@:\[\]-]*)/(system|session)(\?[A-Za-z0-9_=&./-]*)?$#';

/**
 * normalized list: key => [uri, label, readonly, source]; reads the database
 * @phpstan-impure
 */
function connections_list() {
    global $connections, $connection, $readonly;
    $list = [];
    if (!empty($connections) && is_array($connections)) {
        foreach ($connections as $key => $conn) {
            $list[(string)$key] = [
                'uri'      => (string)$conn['uri'],
                'label'    => (string)($conn['label'] ?? $conn['uri']),
                'readonly' => (int)!empty($conn['readonly']),
                'source'   => 'config',
            ];
        }
    }
    else {
        // single connection from older config.php files; function.php keeps the configured
        // values in $config_connection before it sets $connection to the selected connection
        $legacy = $GLOBALS['config_connection'] ?? ['uri' => (string)$connection, 'readonly' => (int)$readonly];
        $list['default'] = ['uri' => $legacy['uri'], 'label' => $legacy['uri'], 'readonly' => $legacy['readonly'], 'source' => 'config'];
    }
    foreach (connection_panel_rows() as $row) {
        $list += [$row['key'] => ['uri' => $row['uri'], 'label' => $row['label'], 'readonly' => (int)$row['readonly'], 'source' => 'panel']];
    }
    return $list;
}

function connection_panel_rows() {
    try {
        return db_query('SELECT * FROM connections ORDER BY label COLLATE NOCASE')->fetchAll();
    }
    catch (PDOException $e) {
        return [];
    }
}

// adds a connection; returns errors
function connection_add($label, $uri, $readonly) {
    $label = trim((string)$label);
    $uri = trim((string)$uri);
    $errors = [];
    if ($label === '' || mb_strlen($label) > 64) {
        $errors[] = t('Name: 1-%d characters.', 64);
    }
    if (!preg_match(CONNECTION_URI_PATTERN, $uri)) {
        $errors[] = t('Invalid connection URI, e.g. qemu+ssh://root@kvm2.example.com/system');
    }
    foreach (connections_list() as $c) {
        if ($c['uri'] === $uri) {
            $errors[] = t('This connection already exists (%s).', $c['label']);
        }
    }
    if ($errors) {
        return $errors;
    }
    // key from the label, unique among all connections
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($label)), '-') ?: 'conn';
    $key = $base;
    for ($i = 2; isset(connections_list()[$key]); $i++) {
        $key = $base.'-'.$i;
    }
    db_query('INSERT INTO connections (key, uri, label, readonly, created_at) VALUES (?, ?, ?, ?, ?)', [$key, $uri, $label, $readonly ? 1 : 0, time()]);
    return [];
}

function connection_delete($key) {
    return db_query('DELETE FROM connections WHERE key = ?', [$key])->rowCount() > 0;
}

// tries the connection with virsh (limited time, ssh never asks); '' or the error
function connection_test($uri) {
    [$status, $output] = cloud_run(['timeout', '15', 'virsh', '-q', '-c', $uri, 'version', '--daemon']);
    if ($status === 0) {
        return '';
    }
    return $status === 124 ? t('No answer within 15 seconds.') : (trim($output) ?: t('Error code %d', $status));
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
