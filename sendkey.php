<?php

// sends a key combination (DOMAIN_KEY_COMBOS) to a running machine; called with
// fetch() from the machine page and the console, answers JSON
require_once 'function.php';

header('Content-Type: application/json');
$fail = function ($code, $message) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    $fail(400, 'Invalid request (CSRF token mismatch).');
}
if (!can('operate') || $readonly) {
    $fail(403, t('You do not have permission for this action.'));
}
$node = (string)($_POST['node'] ?? '');
$key = (string)($_POST['key'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    $fail(400, t('Unknown machine: %s', $node));
}
$text = (string)($_POST['text'] ?? '');
if ($text === '' && !isset(DOMAIN_KEY_COMBOS[$key])) {
    $fail(400, t('Unknown key combination.'));
}
$res = libvirt_domain_lookup_by_name($con, $node);
if (!libvirt_domain_is_active($res)) {
    $fail(409, t('The machine is not running.'));
}
// "type text": every character as a key press (US layout), e.g. a password into a login prompt
if ($text !== '') {
    $codes = mb_strlen($text) <= 1000 ? domain_text_keycodes($text) : null;
    if ($codes === null) {
        $fail(400, t('Only US keyboard characters can be typed (up to 1000), no letters with diacritics.'));
    }
    foreach ($codes as $combo) {
        if (!libvirt_domain_send_key_api($res, 0, 20, $combo)) {
            $fail(500, (string)libvirt_get_last_error());
        }
        usleep(15000);
    }
    action_log('type_text', $node, true, mb_strlen($text).' characters');
    echo json_encode(['ok' => true]);
    exit;
}
// codeset 0 = Linux key codes, hold 100 ms
$ok = (bool)libvirt_domain_send_key_api($res, 0, 100, DOMAIN_KEY_COMBOS[$key][1]);
action_log('send_key', $node, $ok, DOMAIN_KEY_COMBOS[$key][0]);
echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => (string)libvirt_get_last_error()]);
