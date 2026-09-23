<?php

// nginx auth_request endpoint guarding the websockify proxy:
// 204 for a logged in user allowed to use the console, 401 otherwise
define('PUBLIC_PAGE', true);
require_once 'function.php';

http_response_code(can('operate') ? 204 : 401);
