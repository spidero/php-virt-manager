<?php

// SQLite database in the data directory, schema upgraded by numbered migrations

const DB_MIGRATIONS = [
    1 => [
        'CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL CHECK (role IN (\'admin\', \'operator\', \'viewer\')),
            lang TEXT,
            created_at INTEGER NOT NULL,
            last_login INTEGER
        )',
    ],
    2 => [
        'CREATE TABLE jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            params TEXT NOT NULL,
            conn TEXT NOT NULL,
            username TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'queued\' CHECK (status IN (\'queued\', \'running\', \'done\', \'failed\')),
            message TEXT,
            created_at INTEGER NOT NULL,
            started_at INTEGER,
            finished_at INTEGER
        )',
        'CREATE INDEX jobs_status ON jobs (status, id)',
    ],
    3 => [
        'CREATE TABLE schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            conn TEXT NOT NULL,
            domain TEXT NOT NULL,
            frequency TEXT NOT NULL CHECK (frequency IN (\'hourly\', \'daily\', \'weekly\')),
            hour INTEGER NOT NULL DEFAULT 0,
            weekday INTEGER NOT NULL DEFAULT 0,
            keep INTEGER NOT NULL DEFAULT 7,
            enabled INTEGER NOT NULL DEFAULT 1,
            created_by TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_run INTEGER,
            last_status TEXT
        )',
    ],
    4 => [
        'CREATE TABLE api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            prefix TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            last_used INTEGER
        )',
    ],
    5 => [
        // percent of the current phase, NULL when unknown (indeterminate progress bar)
        'ALTER TABLE jobs ADD COLUMN progress INTEGER',
    ],
    6 => [
        'CREATE TABLE ssh_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            public_key TEXT NOT NULL,
            fingerprint TEXT NOT NULL,
            created_at INTEGER NOT NULL,
            UNIQUE (user_id, fingerprint)
        )',
    ],
    7 => [
        // source: list (cloud-images.json / synchronized URL) or local (added in the panel);
        // modified: a list entry edited in the panel, no longer updated by synchronization
        'CREATE TABLE cloud_images (
            key TEXT PRIMARY KEY,
            label TEXT NOT NULL,
            url TEXT NOT NULL,
            source TEXT NOT NULL,
            modified INTEGER NOT NULL DEFAULT 0,
            enabled INTEGER NOT NULL DEFAULT 1,
            updated_at INTEGER NOT NULL
        )',
        'CREATE TABLE settings (
            name TEXT PRIMARY KEY,
            value TEXT
        )',
    ],
    8 => [
        // machines starred by a user, listed in the menu; per connection key
        'CREATE TABLE favorites (
            user_id INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
            conn TEXT NOT NULL,
            name TEXT NOT NULL,
            PRIMARY KEY (user_id, conn, name)
        )',
    ],
    9 => [
        // hypervisor connections added in the panel (those of config.php are not stored)
        'CREATE TABLE connections (
            key TEXT PRIMARY KEY,
            uri TEXT NOT NULL,
            label TEXT NOT NULL,
            readonly INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
        )',
    ],
];

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = db_open(data_path('panel.sqlite'));
    }
    return $pdo;
}

function db_open($path) {
    $pdo = new PDO('sqlite:'.$path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo) {
    $version = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    foreach (DB_MIGRATIONS as $target => $statements) {
        if ($target <= $version) {
            continue;
        }
        $pdo->beginTransaction();
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec('PRAGMA user_version = '.(int)$target);
        $pdo->commit();
    }
}

// runs a query with parameters and returns the statement
function db_query($sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// small persistent values (catalog synchronization state etc.)
function setting_get($name) {
    $value = db_query('SELECT value FROM settings WHERE name = ?', [$name])->fetchColumn();
    return $value === false ? null : $value;
}

function setting_set($name, $value) {
    db_query('INSERT INTO settings (name, value) VALUES (?, ?) ON CONFLICT (name) DO UPDATE SET value = excluded.value', [$name, $value]);
}
