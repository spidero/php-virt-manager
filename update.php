<?php

// "check for updates" from the footer (administrators)
require_once 'function.php';
require_permission('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $state = update_check();
    $running = app_version();
    $newer = update_available($running, $state);
    if (!$state['ok']) {
        flash_set('danger', t('Update check failed: %s', $state['message']));
    }
    elseif ($newer) {
        flash_set('warning', t('Version %s is available.', $newer['version']));
    }
    elseif (preg_match('/^\d+\.\d+\.\d+$/', $running['version'])) {
        flash_set('success', t('You run the newest release (%s).', $running['version']));
    }
    else {
        flash_set('info', t('Development build (%s); the newest release is %s.', $running['version'], $state['version']));
    }
}
$back = (string)($_POST['back'] ?? '');
redirect(preg_match('/^[a-z-]+\.php(\?[A-Za-z0-9=&%._-]*)?$/', $back) ? $back : 'index.php');
