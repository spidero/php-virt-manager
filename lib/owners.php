<?php

// machine owners and quotas: users with the "own" permission (roles user and
// localadmin) see and operate only the machines they own; a machine they create
// becomes theirs, administrators assign owners. Quotas limit what an owner may
// use per hypervisor (number of machines, vCPUs, memory, disk); NULL = no limit.

const QUOTA_FIELDS = ['machines' => 'quota_machines', 'vcpus' => 'quota_vcpus', 'memory_mb' => 'quota_memory_mb', 'disk_gb' => 'quota_disk_gb'];

function machine_owner_id($conn, $name) {
    $id = db_query('SELECT user_id FROM machine_owners WHERE conn = ? AND name = ?', [$conn, $name])->fetchColumn();
    return $id === false ? null : (int)$id;
}

// sets ($user_id) or removes (null) the owner of a machine
function machine_owner_set($conn, $name, $user_id) {
    db_query('DELETE FROM machine_owners WHERE conn = ? AND name = ?', [$conn, $name]);
    if ($user_id !== null) {
        db_query('INSERT INTO machine_owners (conn, name, user_id) VALUES (?, ?, ?)', [$conn, $name, (int)$user_id]);
    }
}

// names of the machines a user owns on a connection
function machine_owned_names($conn, $user_id) {
    return db_query('SELECT name FROM machine_owners WHERE conn = ? AND user_id = ? ORDER BY name', [$conn, (int)$user_id])->fetchAll(PDO::FETCH_COLUMN);
}

// $access: view, operate (power, console, snapshots, edit) or delete
function machine_allowed($access, $name, ?array $user = null, $conn = null) {
    $user ??= current_user();
    if (!$user) {
        return false;
    }
    $role = $user['role'];
    $owner = role_allows($role, 'own') && machine_owner_id($conn ?? connection_current_key(), $name) === (int)$user['id'];
    return match ($access) {
        'view'    => role_allows($role, 'view') || $owner,
        'operate' => role_allows($role, 'operate') || $owner,
        'delete'  => role_allows($role, 'admin') || ($owner && role_allows($role, 'delete_own')),
        default   => false,
    };
}

// the machines of a list the current user may see (all for roles with "view")
function machine_filter_visible(array $names, $conn = null) {
    $user = current_user();
    if (!$user || role_allows($user['role'], 'view')) {
        return $user ? $names : [];
    }
    $own = machine_owned_names($conn ?? connection_current_key(), $user['id']);
    return array_values(array_filter($names, fn($n) => in_array($n, $own, true)));
}

// stops the request when the current user may not access the machine
function require_machine($access, $name, $back_url = 'index.php') {
    if (!machine_allowed($access, $name)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            http_response_code(403);
        }
        flash_set('danger', t('You do not have permission for this action.'));
        redirect($back_url);
    }
}

// after a rename, migration or deletion the ownership follows (or goes away)
function machine_owner_rename($conn, $old, $new) {
    db_query('UPDATE OR REPLACE machine_owners SET name = ? WHERE conn = ? AND name = ?', [$new, $conn, $old]);
}

function machine_owner_move($from_conn, $to_conn, $name) {
    db_query('UPDATE OR REPLACE machine_owners SET conn = ? WHERE conn = ? AND name = ?', [$to_conn, $from_conn, $name]);
}

// quotas of a user: machines, vcpus, memory_mb, disk_gb => int or null (no limit)
function user_quotas(array $user) {
    $quotas = [];
    foreach (QUOTA_FIELDS as $key => $column) {
        // isset() is false for NULL (no limit)
        $quotas[$key] = isset($user[$column]) ? (int)$user[$column] : null;
    }
    return $quotas;
}

// resources the owned machines use on a connection (maximum vCPUs, maximum memory, disk capacity)
function user_usage($con, $conn, $user_id, $except = null) {
    $usage = ['machines' => 0, 'vcpus' => 0, 'memory_mb' => 0, 'disk_gb' => 0];
    $existing = libvirt_list_domains($con) ?: [];
    foreach (machine_owned_names($conn, $user_id) as $name) {
        if ($name === $except || !in_array($name, $existing, true)) {
            continue;
        }
        $res = libvirt_domain_lookup_by_name($con, $name);
        $xml = $res ? (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE) : '';
        if ($xml === '') {
            continue;
        }
        $usage['machines']++;
        $usage['vcpus'] += vm_vcpu_settings($xml)['max'];
        $usage['memory_mb'] += vm_memory_mb($xml);
        $usage['disk_gb'] += quota_disk_gb($con, $res, $xml);
    }
    return $usage;
}

// capacity of the disks (not CD-ROMs) of a machine in GB, rounded up
function quota_disk_gb($con, $res, $xml) {
    $bytes = 0;
    foreach (vm_disk_devices($xml) as $target => $disk) {
        if ($disk['device'] !== 'disk') {
            continue;
        }
        $vol = $disk['path'] !== '' ? @libvirt_storagevolume_lookup_by_path($con, $disk['path']) : false;
        $info = $vol ? @libvirt_storagevolume_get_info($vol) : false;
        $bytes += is_array($info) ? (int)$info['capacity'] : 0;
    }
    return (int)ceil($bytes / 1073741824);
}

// capacity of a volume (by path) in GB, rounded up
function quota_volume_gb($con, $path) {
    $vol = @libvirt_storagevolume_lookup_by_path($con, $path);
    $info = $vol ? @libvirt_storagevolume_get_info($vol) : false;
    return is_array($info) ? (int)ceil($info['capacity'] / 1073741824) : 0;
}

// error message when $add (machines, vcpus, memory_mb, disk_gb) on top of $usage exceeds a quota
function quota_error(array $quotas, array $usage, array $add) {
    $labels = ['machines' => t('machines'), 'vcpus' => 'vCPU', 'memory_mb' => t('memory (MB)'), 'disk_gb' => t('disk (GB)')];
    foreach ($quotas as $key => $limit) {
        $after = $usage[$key] + ($add[$key] ?? 0);
        if ($limit !== null && ($add[$key] ?? 0) > 0 && $after > $limit) {
            return t('Quota exceeded: %s %d of %d (now %d).', $labels[$key], $after, $limit, $usage[$key]);
        }
    }
    return null;
}

// quota check for the current user ('' when it applies no quota or fits)
function quota_check($con, array $add, $except = null) {
    $user = current_user();
    if (!$user || !role_allows($user['role'], 'own')) {
        return '';
    }
    return (string)quota_error(user_quotas($user), user_usage($con, connection_current_key(), $user['id'], $except), $add);
}

// job parameters that make a machine created by an own-machine role its owner's
function owner_job_params() {
    $user = current_user();
    return $user && role_allows($user['role'], 'own') ? ['owner' => (int)$user['id'], 'owner_conn' => connection_current_key()] : [];
}

// called by the jobs and the wizard once a machine exists
function owner_assign_new(array $params, $name) {
    if (!empty($params['owner']) && !empty($params['owner_conn'])) {
        machine_owner_set((string)$params['owner_conn'], $name, (int)$params['owner']);
    }
}

// resources a clone of the machine would add (copied disks only)
function quota_clone_add($con, $res, $xml, array $modes) {
    $disk_gb = 0;
    foreach (vm_disk_devices($xml) as $target => $disk) {
        if ($disk['device'] === 'disk' && ($modes[$target] ?? 'copy') === 'copy') {
            $vol = @libvirt_storagevolume_lookup_by_path($con, $disk['path']);
            $info = $vol ? @libvirt_storagevolume_get_info($vol) : false;
            $disk_gb += is_array($info) ? (int)ceil($info['capacity'] / 1073741824) : 0;
        }
    }
    return ['machines' => 1, 'vcpus' => vm_vcpu_settings($xml)['max'], 'memory_mb' => vm_memory_mb($xml), 'disk_gb' => $disk_gb];
}

// jobs the current user may see: all for "operate", otherwise the own ones
function job_visible(array $job) {
    $user = current_user();
    return $user && (role_allows($user['role'], 'operate') || $job['username'] === $user['username']);
}

// the machine exists and the current user may see it (other users' machines look missing)
function machine_visible($con, $name) {
    return in_array($name, libvirt_list_domains($con) ?: [], true) && machine_allowed('view', $name);
}
