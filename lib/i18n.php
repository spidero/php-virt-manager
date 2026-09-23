<?php

// translations: English source strings are the keys, lang/<code>.php maps them
// to the target language; missing entries fall back to English

const LANGUAGES = ['en' => 'English', 'pl' => 'Polski'];

function i18n_set_language($lang) {
    global $i18n_lang, $i18n_strings;
    $i18n_lang = isset(LANGUAGES[$lang]) ? $lang : 'en';
    $file = __DIR__.'/../lang/'.$i18n_lang.'.php';
    $i18n_strings = ($i18n_lang !== 'en' && is_file($file)) ? require $file : [];
}

function i18n_language() {
    global $i18n_lang;
    return $i18n_lang ?? 'en';
}

// first supported language from the Accept-Language header
function i18n_detect($header) {
    foreach (explode(',', (string)$header) as $part) {
        $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
        if (isset(LANGUAGES[$code])) {
            return $code;
        }
    }
    return 'en';
}

// translated text; extra arguments fill sprintf placeholders
function t($text, ...$args) {
    global $i18n_strings;
    $translated = $i18n_strings[$text] ?? $text;
    return $args ? vsprintf($translated, $args) : $translated;
}

// strings translated at runtime from variables (states, roles, messages kept in arrays)
const I18N_DYNAMIC = [
    'no state', 'running', 'blocked', 'paused', 'shutting down', 'shut off', 'crashed', 'suspended', 'unknown',
    'shutdown', 'shutoff', 'pmsuspended', 'disk-snapshot',
    'inactive', 'building', 'degraded', 'inaccessible',
    'viewer', 'operator', 'admin',
    'Starting machine, it may take some time', 'Shutting down machine, it may take some time',
    'Machine forcibly stopped',
    'clone', 'Hard disk', 'CD/DVD', 'Network (PXE)', 'Rebooting machine, it may take some time', 'Suspending machine', 'Resuming machine',
];

// all strings passed to t() in PHP files and to the |t modifier in templates
function i18n_collect_strings($root) {
    $strings = I18N_DYNAMIC;
    $files = array_merge(glob($root.'/*.php') ?: [], glob($root.'/lib/*.php') ?: [], glob($root.'/templates/*.tpl') ?: []);
    foreach ($files as $file) {
        $code = (string)file_get_contents($file);
        if (str_ends_with($file, '.tpl')) {
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'\\|t\\b/", $code, $m);
        }
        else {
            preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/", $code, $m);
        }
        foreach ($m[1] as $string) {
            $strings[] = stripcslashes($string);
        }
    }
    $strings = array_values(array_unique($strings));
    sort($strings);
    return $strings;
}
