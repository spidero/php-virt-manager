<?php

// nginx auth_request endpoint guarding the websockify proxy:
// 204 for a logged in session, 401 otherwise
define('PUBLIC_PAGE', true);
require_once 'function.php';

http_response_code(empty($_SESSION['user']) ? 401 : 204);
