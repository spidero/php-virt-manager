<?php

// audit log of user actions, one JSON object per line

function action_log($action, $target, $ok, $details = '', $user = null) {
    $entry = [
        'time'    => date('c'),
        'user'    => $user ?? (current_user()['username'] ?? '-'),
        'ip'      => client_ip(),
        'conn'    => function_exists('connection_current_key') ? connection_current_key() : '',
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

// renames actions.log to actions.log.1 (shifting older files) when it exceeds
// $max_bytes; keeps $keep rotated files
function action_log_rotate($max_bytes, $keep) {
    $path = data_path('actions.log');
    clearstatcache(true, $path);
    if (!is_file($path) || filesize($path) < $max_bytes) {
        return false;
    }
    @unlink($path.'.'.$keep);
    for ($i = $keep - 1; $i >= 1; $i--) {
        if (is_file($path.'.'.$i)) {
            rename($path.'.'.$i, $path.'.'.($i + 1));
        }
    }
    rename($path, $path.'.1');
    return true;
}
