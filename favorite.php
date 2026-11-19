<?php

// star/unstar a machine (POST from the machine table or the machine page)
require_once 'function.php';

$name = (string)($_POST['name'] ?? '');
$back = (string)($_POST['back'] ?? '') === 'node' ? 'node.php?node='.urlencode($name) : 'index.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_require();
if (machine_visible($con, $name)) {
    favorite_toggle(current_user()['id'], connection_current_key(), $name);
}
redirect($back);
