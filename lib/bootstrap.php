<?php

// loads the libraries and fills defaults for settings missing in older
// config.php files; shared by web pages, bin/cron.php and tests

require_once __DIR__.'/common.php';
require_once __DIR__.'/i18n.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/users.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/actionlog.php';
require_once __DIR__.'/connections.php';
require_once __DIR__.'/domain.php';
require_once __DIR__.'/console.php';
require_once __DIR__.'/vmedit.php';
require_once __DIR__.'/storage.php';
require_once __DIR__.'/jobs.php';
require_once __DIR__.'/jobhandlers.php';
require_once __DIR__.'/stats.php';
require_once __DIR__.'/cloud.php';
require_once __DIR__.'/schedules.php';
require_once __DIR__.'/api.php';

// $GLOBALS works both at file scope (web, cron) and when included from a function (tests)
$GLOBALS['data_dir']             ??= dirname(__DIR__).'/data';
$GLOBALS['login_max_attempts']   ??= 5;
$GLOBALS['login_lock_seconds']   ??= 900;
$GLOBALS['console_enabled']      ??= false;
$GLOBALS['console_novnc_url']    ??= 'novnc/vnc_lite.html';
$GLOBALS['console_ws_path']      ??= 'websockify';
$GLOBALS['smarty_caching']       ??= 0;
$GLOBALS['auth_user']            ??= 'admin';
$GLOBALS['auth_password_hash']   ??= '';
$GLOBALS['action_log_max_bytes'] ??= 5 * 1024 * 1024;
$GLOBALS['action_log_keep']      ??= 5;
