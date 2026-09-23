<?php

define('PUBLIC_PAGE', true);
require_once 'function.php';

csrf_require();
$_SESSION = [];
session_destroy();
redirect('login.php');
