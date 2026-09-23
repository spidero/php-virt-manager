<?php

// scheduled snapshots with retention, executed by bin/cron.php

const SCHEDULE_FREQUENCIES = ['hourly', 'daily', 'weekly'];
const SCHEDULE_PREFIX = 'auto-';

// start of the most recent scheduled slot at or before $now (local time)
function schedule_slot(array $s, $now) {
    $hour_start = $now - ($now % 60) - (int)date('i', $now) * 60;
    if ($s['frequency'] === 'hourly') {
        return $hour_start;
    }
    $today = mktime((int)$s['hour'], 0, 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
    if ($s['frequency'] === 'daily') {
        return $today <= $now ? $today : strtotime('-1 day', $today);
    }
    // weekly: go back to the configured weekday (0 = Sunday, like date('w'))
    $days_back = ((int)date('w', $now) - (int)$s['weekday'] + 7) % 7;
    $slot = strtotime('-'.$days_back.' days', $today);
    return $slot <= $now ? $slot : strtotime('-7 days', $slot);
}

// a schedule runs once per slot; a new schedule waits for its first slot
function schedule_due(array $s, $now) {
    if (empty($s['enabled'])) {
        return false;
    }
    $slot = schedule_slot($s, $now);
    $since = (int)($s['last_run'] ?? 0) ?: (int)$s['created_at'];
    return $since < $slot;
}

// snapshot names of this schedule sort chronologically
function schedule_snapshot_name(array $s, $now) {
    return SCHEDULE_PREFIX.$s['id'].'-'.date('Ymd-His', $now);
}

// names to delete so that only the newest $keep snapshots of the schedule remain
function schedule_expired(array $s, array $snapshot_names) {
    $prefix = SCHEDULE_PREFIX.$s['id'].'-';
    $own = array_values(array_filter($snapshot_names, fn($n) => str_starts_with($n, $prefix)));
    sort($own);
    return array_slice($own, 0, max(0, count($own) - (int)$s['keep']));
}

function schedule_list($conn = null, $domain = null) {
    $sql = 'SELECT * FROM schedules';
    $params = [];
    if ($conn !== null) {
        $sql .= ' WHERE conn = ?';
        $params[] = $conn;
        if ($domain !== null) {
            $sql .= ' AND domain = ?';
            $params[] = $domain;
        }
    }
    return db_query($sql.' ORDER BY conn, domain, id', $params)->fetchAll();
}

function schedule_create($conn, $domain, $frequency, $hour, $weekday, $keep, $user) {
    db_query('INSERT INTO schedules (conn, domain, frequency, hour, weekday, keep, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$conn, $domain, $frequency, $hour, $weekday, $keep, $user, time()]);
    return (int)db()->lastInsertId();
}

function schedule_find($id) {
    return db_query('SELECT * FROM schedules WHERE id = ?', [$id])->fetch() ?: null;
}

function schedule_set_enabled($id, $enabled) {
    db_query('UPDATE schedules SET enabled = ? WHERE id = ?', [$enabled ? 1 : 0, $id]);
}

function schedule_delete($id) {
    db_query('DELETE FROM schedules WHERE id = ?', [$id]);
}

// runs all due schedules; returns a summary for the cron output
function schedule_run_due($now) {
    $done = [];
    $connections = connections_list();
    foreach (schedule_list() as $s) {
        if (!schedule_due($s, $now)) {
            continue;
        }
        $status = schedule_run($s, $connections[$s['conn']] ?? null, $now);
        db_query('UPDATE schedules SET last_run = ?, last_status = ? WHERE id = ?', [$now, $status['message'], $s['id']]);
        action_log('scheduled_snapshot', $s['domain'], $status['ok'], $status['message'], 'scheduler');
        $done[] = $s['domain'].': '.$status['message'];
    }
    return $done;
}

function schedule_run(array $s, $conn, $now) {
    if (!$conn) {
        return ['ok' => false, 'message' => 'unknown connection '.$s['conn']];
    }
    if ($conn['readonly']) {
        return ['ok' => false, 'message' => 'connection is read-only'];
    }
    $con = libvirt_connect($conn['uri'], false);
    $res = $con ? libvirt_domain_lookup_by_name($con, $s['domain']) : false;
    if (!$res) {
        return ['ok' => false, 'message' => 'machine not found'];
    }
    $name = schedule_snapshot_name($s, $now);
    if (!libvirt_domain_snapshot_create_xml($res, domain_snapshot_xml($name, 'scheduled ('.$s['frequency'].')'))) {
        return ['ok' => false, 'message' => 'snapshot failed: '.libvirt_get_last_error()];
    }
    $deleted = 0;
    foreach (schedule_expired($s, libvirt_list_domain_snapshots($res) ?: []) as $old) {
        $snap = libvirt_domain_snapshot_lookup_by_name($res, $old);
        if ($snap && libvirt_domain_snapshot_delete($snap)) {
            $deleted++;
        }
        // release before the domain, see domain_snapshots()
        unset($snap);
    }
    return ['ok' => true, 'message' => $name.' created'.($deleted ? ', '.$deleted.' old removed' : '')];
}
