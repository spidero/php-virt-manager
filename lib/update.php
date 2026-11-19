<?php

// update check: version.json of the repository (raw file on GitHub, updated with
// every release - CI refuses a release tag that does not match it) is fetched once a
// day by bin/cron.php and on demand; administrators see when a newer release exists

const UPDATE_CHECK_URL = 'https://raw.githubusercontent.com/spidero/php-virt-manager/master/version.json';
const UPDATE_CHECK_INTERVAL = 86400;

// {"version": "X.Y.Z", "url": ...} -> [version, url]; null when unusable
function update_parse($json) {
    $data = json_decode((string)$json, true);
    $version = is_array($data) ? (string)($data['version'] ?? '') : '';
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        return null;
    }
    $url = (string)($data['url'] ?? '');
    return ['version' => $version, 'url' => str_starts_with($url, APP_REPO_URL.'/') ? $url : APP_REPO_URL.'/releases'];
}

// fetches version.json and stores the result (setting "update_check")
function update_check() {
    try {
        $latest = update_parse(cloud_catalog_fetch(UPDATE_CHECK_URL));
        $state = $latest ? ['ok' => true] + $latest : ['ok' => false, 'message' => 'version.json is not valid'];
    }
    catch (RuntimeException $e) {
        $state = ['ok' => false, 'message' => $e->getMessage()];
    }
    $state['time'] = time();
    setting_set('update_check', json_encode($state));
    return $state;
}

function update_last() {
    $state = json_decode((string)setting_get('update_check'), true);
    return is_array($state) ? $state : null;
}

function update_check_due($now) {
    if (empty($GLOBALS['update_check'])) {
        return false;
    }
    $last = update_last();
    return !$last || $now - (int)$last['time'] >= UPDATE_CHECK_INTERVAL;
}

// newer release than the running one: [version, url] or null; only for release
// builds (master and dev builds are ahead of the last release anyway)
function update_available(array $running, ?array $last) {
    if (!$last || empty($last['ok']) || !preg_match('/^\d+\.\d+\.\d+$/', $running['version'])) {
        return null;
    }
    return version_compare($last['version'], $running['version'], '>') ? ['version' => $last['version'], 'url' => $last['url']] : null;
}
