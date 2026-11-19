<?php

// catalog of cloud images: stored in the database, seeded from the bundled
// cloud-images.json and synchronized from a URL (by default the same file on
// GitHub). Synchronization only adds and updates: entries edited in the panel
// are kept, nothing is removed. Entries from the list can be disabled, entries
// added in the panel can also be deleted.

const CLOUD_CATALOG_FILE = __DIR__.'/../cloud-images.json';
const CLOUD_CATALOG_DEFAULT_URL = 'https://raw.githubusercontent.com/spidero/php-virt-manager/master/cloud-images.json';
const CLOUD_KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,63}$/';
const CLOUD_LABEL_MAX = 64;
const CLOUD_CATALOG_MAX_BYTES = 1048576;
const CLOUD_CATALOG_SYNC_INTERVAL = 86400;

// validation errors of one catalog entry
function cloud_image_errors($key, $label, $url) {
    $errors = [];
    if (!preg_match(CLOUD_KEY_PATTERN, (string)$key)) {
        $errors[] = t('Key: lowercase letters, digits, . _ - (up to 64 characters).');
    }
    $label = trim((string)$label);
    if ($label === '' || mb_strlen($label) > CLOUD_LABEL_MAX) {
        $errors[] = t('Name: 1-%d characters.', CLOUD_LABEL_MAX);
    }
    if (!preg_match('#^https://[^\s/]+/\S+$#', (string)$url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        $errors[] = t('URL must be an https:// address of the image file.');
    }
    return $errors;
}

// entries of a catalog file: {"version": 1, "images": [{"key", "label", "url"}]};
// invalid entries are skipped, an unusable file throws
function cloud_catalog_parse($json) {
    $data = json_decode((string)$json, true);
    if (!is_array($data) || !is_array($data['images'] ?? null)) {
        throw new RuntimeException('not a catalog file (expected {"version": 1, "images": [...]})');
    }
    if ((int)($data['version'] ?? 0) !== 1) {
        throw new RuntimeException('unsupported catalog version '.json_encode($data['version'] ?? null));
    }
    $images = [];
    $invalid = 0;
    foreach ($data['images'] as $item) {
        $key = is_array($item) ? (string)($item['key'] ?? '') : '';
        $label = is_array($item) ? trim((string)($item['label'] ?? '')) : '';
        $url = is_array($item) ? (string)($item['url'] ?? '') : '';
        if (cloud_image_errors($key, $label, $url) || isset($images[$key])) {
            $invalid++;
            continue;
        }
        $images[$key] = ['label' => $label, 'url' => $url];
    }
    if (!$images) {
        throw new RuntimeException('the catalog contains no valid images');
    }
    return ['images' => $images, 'invalid' => $invalid];
}

// adds new entries and updates those not edited in the panel
function cloud_catalog_merge(array $images) {
    $result = ['added' => 0, 'updated' => 0, 'kept' => 0];
    foreach ($images as $key => $image) {
        $row = db_query('SELECT * FROM cloud_images WHERE key = ?', [$key])->fetch();
        if (!$row) {
            db_query('INSERT INTO cloud_images (key, label, url, source, modified, enabled, updated_at) VALUES (?, ?, ?, ?, 0, 1, ?)',
                [$key, $image['label'], $image['url'], 'list', time()]);
            $result['added']++;
        }
        elseif ($row['source'] === 'list' && !$row['modified']) {
            if ($row['label'] !== $image['label'] || $row['url'] !== $image['url']) {
                db_query('UPDATE cloud_images SET label = ?, url = ?, updated_at = ? WHERE key = ?',
                    [$image['label'], $image['url'], time(), $key]);
                $result['updated']++;
            }
        }
        else {
            $result['kept']++;
        }
    }
    return $result;
}

// first use: the bundled list
function cloud_catalog_seed() {
    if (setting_get('cloud_catalog_seeded') !== null) {
        return;
    }
    $file = @file_get_contents(CLOUD_CATALOG_FILE);
    if ($file !== false) {
        cloud_catalog_merge(cloud_catalog_parse($file)['images']);
    }
    setting_set('cloud_catalog_seeded', (string)time());
}

// catalog used by the panel: key => [label, url]; $all includes disabled entries
// (an already downloaded image stays usable); $cloud_images from config.php is added
function cloud_catalog($all = false) {
    global $cloud_images;
    cloud_catalog_seed();
    $catalog = [];
    foreach (db_query('SELECT key, label, url FROM cloud_images'.($all ? '' : ' WHERE enabled = 1').' ORDER BY rowid')->fetchAll() as $row) {
        $catalog[$row['key']] = ['label' => $row['label'], 'url' => $row['url']];
    }
    return array_merge($catalog, is_array($cloud_images ?? null) ? $cloud_images : []);
}

// all entries with their state, for the catalog page
function cloud_catalog_rows() {
    cloud_catalog_seed();
    return db_query('SELECT * FROM cloud_images ORDER BY rowid')->fetchAll();
}

// add ($new) or edit an entry; returns validation errors
function cloud_image_save($key, $label, $url, $new) {
    $label = trim((string)$label);
    $url = trim((string)$url);
    $errors = cloud_image_errors($key, $label, $url);
    $row = db_query('SELECT * FROM cloud_images WHERE key = ?', [$key])->fetch();
    if ($new && $row) {
        $errors[] = t('An image with this key already exists.');
    }
    if (!$new && !$row) {
        $errors[] = t('Image not found.');
    }
    if ($errors) {
        return $errors;
    }
    if ($new) {
        db_query('INSERT INTO cloud_images (key, label, url, source, modified, enabled, updated_at) VALUES (?, ?, ?, ?, 0, 1, ?)',
            [$key, $label, $url, 'local', time()]);
    }
    elseif ($row['label'] !== $label || $row['url'] !== $url) {
        // an edited list entry is no longer updated by synchronization
        db_query('UPDATE cloud_images SET label = ?, url = ?, modified = 1, updated_at = ? WHERE key = ?',
            [$label, $url, time(), $key]);
    }
    return [];
}

function cloud_image_set_enabled($key, $enabled) {
    return db_query('UPDATE cloud_images SET enabled = ? WHERE key = ?', [$enabled ? 1 : 0, $key])->rowCount() > 0;
}

// an entry from the list would come back with the next synchronization: disable it instead
function cloud_image_delete($key) {
    return db_query("DELETE FROM cloud_images WHERE key = ? AND source = 'local'", [$key])->rowCount() > 0;
}

// a list entry edited in the panel: back to the values of the list on the next synchronization
function cloud_image_reset($key) {
    return db_query("UPDATE cloud_images SET modified = 0 WHERE key = ? AND source = 'list'", [$key])->rowCount() > 0;
}

function cloud_catalog_url() {
    $url = (string)($GLOBALS['cloud_catalog_url'] ?? '');
    return $url !== '' ? $url : CLOUD_CATALOG_DEFAULT_URL;
}

// downloads the list (https only, size limited)
function cloud_catalog_fetch($url) {
    if (!preg_match('#^https://#', $url)) {
        throw new RuntimeException('the catalog URL must start with https://');
    }
    $body = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_FAILONERROR    => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT      => 'php-virt-manager',
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body) {
            $body .= $chunk;
            // returning less than the chunk length aborts the transfer
            return strlen($body) > CLOUD_CATALOG_MAX_BYTES ? 0 : strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $error = curl_error($ch);
    if (!$ok) {
        throw new RuntimeException(strlen($body) > CLOUD_CATALOG_MAX_BYTES ? 'the catalog is larger than 1 MB' : 'download failed: '.$error);
    }
    return $body;
}

// synchronization from the catalog URL; the result is stored for the catalog page
function cloud_catalog_sync($url = null) {
    $url = $url ?? cloud_catalog_url();
    cloud_catalog_seed();
    try {
        $parsed = cloud_catalog_parse(cloud_catalog_fetch($url));
        $result = cloud_catalog_merge($parsed['images']) + ['invalid' => $parsed['invalid']];
        $state = ['ok' => true, 'message' => sprintf('%d added, %d updated, %d kept (edited in the panel), %d invalid skipped',
            $result['added'], $result['updated'], $result['kept'], $result['invalid'])] + $result;
    }
    catch (RuntimeException $e) {
        $state = ['ok' => false, 'message' => $e->getMessage()];
    }
    $state += ['time' => time(), 'url' => $url];
    setting_set('cloud_catalog_sync', json_encode($state));
    return $state;
}

function cloud_catalog_last_sync() {
    $state = json_decode((string)setting_get('cloud_catalog_sync'), true);
    return is_array($state) ? $state : null;
}

// automatic synchronization (bin/cron.php): once a day unless disabled in config.php
function cloud_catalog_sync_due($now) {
    if (empty($GLOBALS['cloud_catalog_auto_sync'])) {
        return false;
    }
    $last = cloud_catalog_last_sync();
    return !$last || $now - (int)$last['time'] >= CLOUD_CATALOG_SYNC_INTERVAL;
}
