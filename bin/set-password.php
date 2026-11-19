<?php

// Applies $auth_user / $auth_password_hash from config.php to the user database:
// the stored password of that account is overwritten (or the account is created
// as admin). config.php is only used for the very first user otherwise, so run
// this after changing the hash there. The Docker entrypoint runs it whenever
// PVM_PASSWORD or PVM_PASSWORD_HASH is set.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/config.php';
require_once $root.'/vendor/autoload.php';
require_once $root.'/lib/bootstrap.php';

/** @var string $auth_user */
/** @var string $auth_password_hash */
if ($auth_user === '' || $auth_password_hash === '') {
    fwrite(STDERR, "\$auth_user and \$auth_password_hash must be set in config.php\n");
    exit(1);
}

$result = user_set_password($auth_user, $auth_password_hash);
echo "Password of user '$auth_user' $result.\n";
