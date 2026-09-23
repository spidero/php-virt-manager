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
