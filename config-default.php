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

// enable/disable smarty caching
$smarty_caching = 0;
