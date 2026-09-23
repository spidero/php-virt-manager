<?php

// loads the libraries without config.php or the libvirt extension;
// data (SQLite, logs) goes to a temporary directory

require dirname(__DIR__).'/vendor/autoload.php';

if (!extension_loaded('libvirt')) {
    // constants only, functions are not called by the tests
    foreach (['VIR_DOMAIN_XML_INACTIVE' => 2] as $name => $value) {
        define($name, $value);
    }
}

$data_dir = $GLOBALS['data_dir'] = sys_get_temp_dir().'/pvm-tests-'.getmypid();
require dirname(__DIR__).'/lib/bootstrap.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

register_shutdown_function(function () use ($data_dir) {
    foreach (glob($data_dir.'/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($data_dir);
});
