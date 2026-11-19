<?php

// nginx auth_request endpoint guarding the websockify proxy:
// 204 for a logged in user allowed to use the console, 401 otherwise
define('PUBLIC_PAGE', true);
require_once 'function.php';

// the machine is checked by console.php, this only stops users without any machine access
http_response_code(can('operate') || can('own') ? 204 : 401);
