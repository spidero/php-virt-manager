<?php

// brute force protection: after $login_max_attempts failures within
// $login_lock_seconds the client IP is locked for $login_lock_seconds

function login_attempts_file() {
    return data_path('login_attempts.json');
}

// seconds until the lock expires, 0 when not locked
function login_locked_for($ip) {
    global $login_max_attempts, $login_lock_seconds;
    $data = json_decode((string)@file_get_contents(login_attempts_file()), true) ?: [];
    $entry = $data[$ip] ?? null;
    if (!$entry || $entry['count'] < $login_max_attempts) {
        return 0;
    }
    return max(0, $entry['first'] + $login_lock_seconds - time());
}

function login_register_failure($ip) {
    global $login_lock_seconds;
    json_file_update(login_attempts_file(), function ($data) use ($ip, $login_lock_seconds) {
        $now = time();
        // drop expired entries
        foreach ($data as $key => $entry) {
            if ($entry['first'] + $login_lock_seconds < $now) {
                unset($data[$key]);
            }
        }
        $data[$ip] = $data[$ip] ?? ['count' => 0, 'first' => $now];
        $data[$ip]['count']++;
        return $data;
    });
}

function login_reset($ip) {
    json_file_update(login_attempts_file(), function ($data) use ($ip) {
        unset($data[$ip]);
        return $data;
    });
}
