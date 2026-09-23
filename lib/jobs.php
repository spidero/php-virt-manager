<?php

// background jobs (long-running libvirt operations) executed by bin/cron.php;
// handlers are registered in JOB_HANDLERS as type => function(array $params, $con, int $job_id, string $uri): string

const JOB_KEEP_SECONDS = 7 * 24 * 3600;

// job type => label shown on the tasks page (translated)
const JOB_LABELS = [
    'clone'          => 'Cloning',
    'image_download' => 'Image download',
    'cloud_create'   => 'Machine from cloud image',
];

function job_create($type, array $params) {
    db_query('INSERT INTO jobs (type, params, conn, username, created_at) VALUES (?, ?, ?, ?, ?)', [
        $type, json_encode($params), connection_current_key(), current_user()['username'] ?? '-', time(),
    ]);
    return (int)db()->lastInsertId();
}

function job_list($limit = 50) {
    $jobs = db_query('SELECT * FROM jobs ORDER BY id DESC LIMIT '.(int)$limit)->fetchAll();
    foreach ($jobs as &$job) {
        $job['params'] = json_decode($job['params'], true) ?: [];
        $job['label'] = JOB_LABELS[$job['type']] ?? $job['type'];
    }
    return $jobs;
}

function job_active_count() {
    return (int)db_query("SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'running')")->fetchColumn();
}

// true when a queued/running job of the type has the parameter value (e.g. a clone target name)
function job_pending($type, $param, $value) {
    return job_find_pending($type, $param, $value) !== null;
}

// takes the oldest queued job; the conditional update makes it safe against a second worker
function job_claim() {
    $job = db_query("SELECT * FROM jobs WHERE status = 'queued' ORDER BY id LIMIT 1")->fetch();
    if (!$job) {
        return null;
    }
    $claimed = db_query("UPDATE jobs SET status = 'running', started_at = ? WHERE id = ? AND status = 'queued'", [time(), $job['id']]);
    return $claimed->rowCount() ? $job : null;
}

// progress of a running job shown on the tasks page: text of the current phase
// and its percent (null = unknown, shown as an animated bar)
function job_progress($id, $message, $percent = null) {
    db_query("UPDATE jobs SET message = ?, progress = ? WHERE id = ? AND status = 'running'",
        [mb_substr((string)$message, 0, 2000), $percent === null ? null : max(0, min(100, (int)$percent)), $id]);
}

// queued or running job of the type whose parameter has the value, null when none
function job_find_pending($type, $param, $value) {
    foreach (db_query("SELECT * FROM jobs WHERE type = ? AND status IN ('queued', 'running') ORDER BY id", [$type])->fetchAll() as $job) {
        if ((json_decode($job['params'], true)[$param] ?? null) === $value) {
            return $job;
        }
    }
    return null;
}

// state of the given jobs for the progress bars (polled by the browser)
function job_states(array $ids) {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return [];
    }
    $rows = db_query('SELECT id, status, progress, message FROM jobs WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')', $ids)->fetchAll();
    $states = [];
    foreach ($rows as $row) {
        $states[$row['id']] = ['status' => $row['status'], 'progress' => $row['progress'] === null ? null : (int)$row['progress'], 'message' => (string)$row['message']];
    }
    return $states;
}

function job_finish($id, $ok, $message) {
    db_query('UPDATE jobs SET status = ?, message = ?, progress = NULL, finished_at = ? WHERE id = ?',
        [$ok ? 'done' : 'failed', mb_substr((string)$message, 0, 2000), time(), $id]);
}

// jobs left "running" by a crashed worker are marked failed
function job_fail_stale($max_seconds) {
    return db_query("UPDATE jobs SET status = 'failed', message = 'worker stopped', finished_at = ? WHERE status = 'running' AND started_at < ?",
        [time(), time() - $max_seconds])->rowCount();
}

function job_cleanup() {
    return db_query("DELETE FROM jobs WHERE status IN ('done', 'failed') AND finished_at < ?", [time() - JOB_KEEP_SECONDS])->rowCount();
}

// runs queued jobs until none is left or $time_limit seconds passed
function job_run_queue(array $handlers, $time_limit) {
    $start = time();
    $done = [];
    while (time() - $start < $time_limit && ($job = job_claim())) {
        $params = json_decode($job['params'], true) ?: [];
        $conn = connections_list()[$job['conn']] ?? null;
        try {
            if (!isset($handlers[$job['type']])) {
                throw new RuntimeException('unknown job type '.$job['type']);
            }
            if (!$conn) {
                throw new RuntimeException('unknown connection '.$job['conn']);
            }
            $con = libvirt_connect($conn['uri'], false);
            if (!$con) {
                throw new RuntimeException('cannot connect: '.libvirt_get_last_error());
            }
            $message = $handlers[$job['type']]($params, $con, (int)$job['id'], $conn['uri']);
            job_finish($job['id'], true, $message);
            action_log($job['type'], $params['name'] ?? '', true, $message, $job['username']);
        }
        catch (Throwable $e) {
            job_finish($job['id'], false, $e->getMessage());
            action_log($job['type'], $params['name'] ?? '', false, $e->getMessage(), $job['username']);
        }
        $done[] = '#'.$job['id'].' '.$job['type'];
    }
    return $done;
}
