<?php

define('PUBLIC_PAGE', true);
require_once 'function.php';

csrf_require();
action_log('logout', $_SESSION['user'] ?? '-', true);
$_SESSION = [];
session_destroy();
redirect('login.php');
