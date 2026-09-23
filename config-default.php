<?php
// Copy this file to config.php and adjust the settings below.

// libvirt connections (hypervisors); users switch between them in the menu.
// readonly = 1 blocks all changes on that connection. Remote hosts over SSH
// (qemu+ssh://user@host/system) need an SSH key for the web server user;
// the browser console works for local connections only.
$connections = [
    'local' => ['uri' => 'qemu:///system', 'label' => 'Local (qemu:///system)', 'readonly' => 0],
    // 'lab' => ['uri' => 'qemu+ssh://admin@lab.example.com/system', 'label' => 'Lab server', 'readonly' => 0],
];

// first administrator, created in the user database on the first run
// (later manage users in the panel); generate the hash with:
// php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
$auth_user = 'admin';
$auth_password_hash = '';

// failed logins allowed per IP before it is locked for $login_lock_seconds
$login_max_attempts = 5;
$login_lock_seconds = 900;

// writable directory for the user database, action log, login lock data and
// console tokens (must not be served by the web server)
$data_dir = __DIR__.'/data';

// action log rotation (bin/cron.php): size limit and number of kept files
$action_log_max_bytes = 5 * 1024 * 1024;
$action_log_keep = 5;

// browser console (noVNC + websockify), requires the nginx deployment
// from deploy/ or the Docker image; paths are relative to the panel URL
$console_enabled = false;
$console_novnc_url = 'novnc/vnc_lite.html';
$console_ws_path = 'websockify';

// enable/disable smarty caching
$smarty_caching = 0;
