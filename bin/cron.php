<?php

// periodic tasks, run every minute by a systemd timer (host) or a loop in
// supervisord (Docker): action log rotation, console token cleanup and the
// task hooks registered in CRON_TASKS

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/config.php';
require_once $root.'/vendor/autoload.php';
require_once $root.'/lib/bootstrap.php';

// only one instance at a time
$lock = fopen(data_path('cron.lock'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

i18n_set_language('en');

$tasks = [
    'action log rotation' => fn() => action_log_rotate($action_log_max_bytes, $action_log_keep) ? 'rotated' : '',
    'console tokens'      => fn() => ($n = console_tokens_cleanup()) ? $n.' removed' : '',
];

$status = 0;
foreach ($tasks as $name => $task) {
    try {
        $result = $task();
        if ($result !== '') {
            echo date('c'), ' ', $name, ': ', $result, "\n";
        }
    }
    catch (Throwable $e) {
        fwrite(STDERR, date('c').' '.$name.' failed: '.$e->getMessage()."\n");
        $status = 1;
    }
}

flock($lock, LOCK_UN);
exit($status);
