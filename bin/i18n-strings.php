<?php

// lists translatable strings used in templates and PHP code;
// with a language code prints the ones missing in lang/<code>.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/lib/i18n.php';

$strings = i18n_collect_strings($root);
$lang = $argv[1] ?? null;
if ($lang === null) {
    echo implode("\n", $strings), "\n";
    exit(0);
}
$translations = require $root.'/lang/'.basename($lang).'.php';
$missing = array_values(array_filter($strings, fn($s) => !isset($translations[$s])));
echo implode("\n", $missing), ($missing ? "\n" : '');
exit($missing ? 1 : 0);
