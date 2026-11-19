<?php

// personal SSH public keys of panel users, offered in the cloud image wizard

const SSH_KEY_NAME_MAX = 64;

// ['type', 'key' (normalized line), 'comment', 'fingerprint'] or null when the
// line is not a single valid public key
function ssh_key_parse($line) {
    $line = trim((string)$line);
    if (preg_match('/\R/', $line) || cloud_ssh_keys($line) !== [$line]) {
        return null;
    }
    $parts = preg_split('/\s+/', $line, 3);
    $blob = base64_decode($parts[1], true);
    // the blob starts with the key type as an SSH string (uint32 length + bytes)
    if ($blob === false || strlen($blob) < 4) {
        return null;
    }
    $len = unpack('N', substr($blob, 0, 4))[1];
    if (substr($blob, 4, $len) !== $parts[0]) {
        return null;
    }
    $comment = $parts[2] ?? '';
    return [
        'type'        => $parts[0],
        'key'         => $parts[0].' '.$parts[1].($comment !== '' ? ' '.$comment : ''),
        'comment'     => $comment,
        // same format as "ssh-keygen -l": SHA256 of the blob, base64 without padding
        'fingerprint' => 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '='),
    ];
}

function ssh_key_list($user_id) {
    return db_query('SELECT id, name, public_key, fingerprint, created_at FROM ssh_keys WHERE user_id = ? ORDER BY name COLLATE NOCASE, id',
        [$user_id])->fetchAll();
}

// null on success, otherwise an error message
function ssh_key_add($user_id, $name, $public_key) {
    $key = ssh_key_parse($public_key);
    if (!$key) {
        return t('This is not a valid SSH public key (paste one line, e.g. from ~/.ssh/id_ed25519.pub).');
    }
    $name = trim((string)$name);
    if ($name === '') {
        $name = $key['comment'] !== '' ? $key['comment'] : $key['type'];
    }
    $name = mb_substr($name, 0, SSH_KEY_NAME_MAX);
    if (db_query('SELECT 1 FROM ssh_keys WHERE user_id = ? AND fingerprint = ?', [$user_id, $key['fingerprint']])->fetchColumn()) {
        return t('This key is already saved.');
    }
    db_query('INSERT INTO ssh_keys (user_id, name, public_key, fingerprint, created_at) VALUES (?, ?, ?, ?, ?)',
        [$user_id, $name, $key['key'], $key['fingerprint'], time()]);
    return null;
}

function ssh_key_delete($user_id, $id) {
    return db_query('DELETE FROM ssh_keys WHERE id = ? AND user_id = ?', [$id, $user_id])->rowCount() > 0;
}

// public keys of the given ids, only those owned by the user
function ssh_keys_by_ids($user_id, array $ids) {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    return db_query("SELECT public_key FROM ssh_keys WHERE user_id = ? AND id IN ($marks) ORDER BY id",
        array_merge([$user_id], $ids))->fetchAll(PDO::FETCH_COLUMN);
}
