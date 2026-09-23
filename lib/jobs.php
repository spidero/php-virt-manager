<?php

// background jobs (long-running libvirt operations) executed by bin/cron.php;
// handlers are registered in JOB_HANDLERS as type => function(array $params, $con): string

const JOB_KEEP_SECONDS = 7 * 24 * 3600;

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
    }
    return $jobs;
}

function job_active_count() {
    return (int)db_query("SELECT COUNT(*) FROM jobs WHERE status IN ('queued', 'running')")->fetchColumn();
}

// true when a queued/running job of the type has the parameter value (e.g. a clone target name)
function job_pending($type, $param, $value) {
    foreach (db_query("SELECT params FROM jobs WHERE type = ? AND status IN ('queued', 'running')", [$type])->fetchAll() as $row) {
        if ((json_decode($row['params'], true)[$param] ?? null) === $value) {
            return true;
        }
    }
    return false;
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

function job_finish($id, $ok, $message) {
    db_query('UPDATE jobs SET status = ?, message = ?, finished_at = ? WHERE id = ?',
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
            $message = $handlers[$job['type']]($params, $con);
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
