<?php

// audit log of user actions, one JSON object per line

function action_log($action, $target, $ok, $details = '') {
    $entry = [
        'time'    => date('c'),
        'user'    => $_SESSION['user'] ?? '-',
        'ip'      => client_ip(),
        'action'  => $action,
        'target'  => $target,
        'ok'      => (bool)$ok,
        'details' => $details,
    ];
    file_put_contents(data_path('actions.log'), json_encode($entry)."\n", FILE_APPEND | LOCK_EX);
}

// newest entries first
function action_log_read($limit = 200) {
    $path = data_path('actions.log');
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $entries = [];
    foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) {
            $entries[] = $entry;
        }
    }
    return $entries;
}
