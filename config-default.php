<?php
// Copy this file to config.php and adjust the settings below.

// local connection to qemu
$connection = 'qemu:///system';

// connection in readonly 0-no, 1-yes
$readonly = 0;

// login credentials; generate the hash with:
// php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
// an empty hash disables login entirely
$auth_user = 'admin';
$auth_password_hash = '';

// failed logins allowed per IP before it is locked for $login_lock_seconds
$login_max_attempts = 5;
$login_lock_seconds = 900;

// writable directory for the action log, login lock data and console tokens
// (must not be served by the web server)
$data_dir = __DIR__.'/data';

// browser console (noVNC + websockify), requires the nginx deployment
// from deploy/ or the Docker image; paths are relative to the panel URL
$console_enabled = false;
$console_novnc_url = 'novnc/vnc_lite.html';
$console_ws_path = 'websockify';

// enable/disable smarty caching
$smarty_caching = 0;
