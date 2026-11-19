<?php

// closes a notice for the current user (POST from the close button)
require_once 'function.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    notice_dismiss(current_user()['id'], (string)($_POST['notice'] ?? ''));
}
redirect('index.php');
