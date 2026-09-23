<?php

// REST API (api.php/v1/...): Bearer token authentication, JSON in and out,
// permissions of the token owner's role

class ApiError extends RuntimeException {}

// method, path pattern, handler, required permission
const API_ROUTES = [
    ['GET',    '#^/v1/connections$#',                              'api_connections',     'view'],
    ['GET',    '#^/v1/domains$#',                                  'api_domains',         'view'],
    ['GET',    '#^/v1/domains/([^/]+)$#',                          'api_domain',          'view'],
    ['GET',    '#^/v1/domains/([^/]+)/stats$#',                    'api_domain_stats',    'view'],
    ['POST',   '#^/v1/domains/([^/]+)/actions/([a-z]+)$#',         'api_domain_action',   'operate'],
    ['GET',    '#^/v1/domains/([^/]+)/snapshots$#',                'api_snapshots',       'view'],
    ['POST',   '#^/v1/domains/([^/]+)/snapshots$#',                'api_snapshot_create', 'operate'],
    ['POST',   '#^/v1/domains/([^/]+)/snapshots/([^/]+)/revert$#', 'api_snapshot_revert', 'operate'],
    ['DELETE', '#^/v1/domains/([^/]+)/snapshots/([^/]+)$#',        'api_snapshot_delete', 'operate'],
    ['POST',   '#^/v1/domains/([^/]+)/clone$#',                    'api_domain_clone',    'operate'],
    ['POST',   '#^/v1/cloud$#',                                    'api_cloud_create',    'operate'],
    ['GET',    '#^/v1/cloud/images$#',                             'api_cloud_images',    'view'],
    ['GET',    '#^/v1/jobs$#',                                     'api_jobs',            'operate'],
    ['GET',    '#^/v1/jobs/([0-9]+)$#',                            'api_job',             'operate'],
    ['GET',    '#^/v1/networks$#',                                 'api_networks',        'view'],
    ['GET',    '#^/v1/pools$#',                                    'api_pools',           'view'],
];

// [handler, permission, arguments]; throws ApiError 404/405
function api_route($method, $path) {
    $allowed = [];
    foreach (API_ROUTES as [$route_method, $pattern, $handler, $permission]) {
        if (preg_match($pattern, $path, $m)) {
            if ($route_method === $method) {
                return [$handler, $permission, array_map('rawurldecode', array_slice($m, 1))];
            }
            $allowed[] = $route_method;
        }
    }
    throw new ApiError($allowed ? 'method not allowed, use '.implode(', ', $allowed) : 'not found', $allowed ? 405 : 404);
}

// token from "Authorization: Bearer <token>"
function api_bearer_token(array $server) {
    $header = (string)($server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : '';
}

function api_json_body() {
    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new ApiError('request body must be a JSON object', 400);
    }
    return $data;
}

function api_lookup($con, $name) {
    if (!in_array($name, libvirt_list_domains($con) ?: [], true)) {
        throw new ApiError('machine not found', 404);
    }
    return libvirt_domain_lookup_by_name($con, $name);
}

function api_require_writable() {
    if (connection_current()['readonly']) {
        throw new ApiError('connection is read-only', 403);
    }
}

// libvirt call result: logs the action and throws on failure
function api_libvirt_result($ok, $action, $target, $details = '') {
    $error = $ok ? '' : (string)libvirt_get_last_error();
    action_log($action, $target, (bool)$ok, trim($details.' '.$error.' (api)'));
    if (!$ok) {
        throw new ApiError($error ?: 'libvirt error', 409);
    }
}

function api_connections($con) {
    $list = [];
    foreach (connections_list() as $key => $c) {
        $list[] = ['key' => $key, 'label' => $c['label'], 'uri' => $c['uri'], 'readonly' => (bool)$c['readonly'], 'current' => $key === connection_current_key()];
    }
    return $list;
}

function api_domains($con) {
    return array_map(fn($d) => ['name' => $d['name'], 'state' => $d['label'], 'state_id' => $d['id']], domain_list($con));
}

function api_domain($con, $name) {
    $res = api_lookup($con, $name);
    $info = libvirt_domain_get_info($res);
    $active = (bool)libvirt_domain_is_active($res);
    $xml = domain_xml($res);
    return [
        'name'       => $name,
        'uuid'       => libvirt_domain_get_uuid_string($res),
        'state'      => domain_state($info['state'])['label'],
        'memory_mb'  => (int)round($info['memory'] / 1024),
        'vcpus'      => (int)$info['nrVirtCpu'],
        'autostart'  => (bool)libvirt_domain_get_autostart($res),
        'disks'      => $xml ? domain_disks($res, $xml) : [],
        'interfaces' => $xml ? domain_interfaces($res, $xml, $active) : [],
        'graphics'   => $xml ? domain_graphics($xml) : null,
    ];
}

function api_domain_stats($con, $name) {
    api_lookup($con, $name);
    $stats = @libvirt_connect_get_all_domain_stats($con, 0, 0) ?: [];
    return ['time' => microtime(true), 'counters' => isset($stats[$name]) ? stats_counters($stats[$name]) : null];
}

function api_domain_action($con, $name, $action) {
    api_require_writable();
    $res = api_lookup($con, $name);
    if (!isset(DOMAIN_POWER_ACTIONS[$action])) {
        throw new ApiError('unknown action, use: '.implode(', ', array_keys(DOMAIN_POWER_ACTIONS)), 400);
    }
    api_libvirt_result(DOMAIN_POWER_ACTIONS[$action][0]($res), $action, $name);
    return ['ok' => true, 'message' => DOMAIN_POWER_ACTIONS[$action][1]];
}

function api_snapshots($con, $name) {
    // keep the domain in a variable until the snapshots are released
    $res = api_lookup($con, $name);
    return domain_snapshots($res);
}

function api_snapshot_create($con, $name) {
    api_require_writable();
    $res = api_lookup($con, $name);
    $body = api_json_body();
    $snapshot = (string)($body['name'] ?? date('Y-m-d_H-i-s'));
    if (!preg_match(DOMAIN_NAME_PATTERN, $snapshot)) {
        throw new ApiError('invalid snapshot name', 400);
    }
    api_libvirt_result(libvirt_domain_snapshot_create_xml($res, domain_snapshot_xml($snapshot, (string)($body['description'] ?? ''))),
        'snapshot_create', $name, $snapshot);
    return ['ok' => true, 'name' => $snapshot];
}

// runs $operation on the snapshot; the snapshot resource is released before
// the domain (see the note at domain_snapshots())
function api_snapshot_call($con, $name, $snapshot, $operation) {
    $res = api_lookup($con, $name);
    if (!in_array($snapshot, libvirt_list_domain_snapshots($res) ?: [], true)) {
        throw new ApiError('snapshot not found', 404);
    }
    $snap = libvirt_domain_snapshot_lookup_by_name($res, $snapshot);
    $ok = $operation($snap);
    unset($snap);
    return $ok;
}

function api_snapshot_revert($con, $name, $snapshot) {
    api_require_writable();
    api_libvirt_result(api_snapshot_call($con, $name, $snapshot, 'libvirt_domain_snapshot_revert'), 'snapshot_revert', $name, $snapshot);
    return ['ok' => true];
}

function api_snapshot_delete($con, $name, $snapshot) {
    api_require_writable();
    api_libvirt_result(api_snapshot_call($con, $name, $snapshot, 'libvirt_domain_snapshot_delete'), 'snapshot_delete', $name, $snapshot);
    return ['ok' => true];
}

function api_domain_clone($con, $name) {
    api_require_writable();
    $res = api_lookup($con, $name);
    $target = (string)(api_json_body()['name'] ?? '');
    if (libvirt_domain_is_active($res)) {
        throw new ApiError('shut the machine down first', 409);
    }
    if (!preg_match(DOMAIN_NAME_PATTERN, $target)) {
        throw new ApiError('invalid name', 400);
    }
    if (in_array($target, libvirt_list_domains($con) ?: [], true) || job_pending('clone', 'name', $target)) {
        throw new ApiError('machine already exists', 409);
    }
    $id = job_create('clone', ['source' => $name, 'name' => $target]);
    action_log('clone_queued', $name, true, $target.' (job #'.$id.', api)');
    return ['ok' => true, 'job' => $id];
}

function api_cloud_images($con) {
    $downloaded = cloud_downloaded($con);
    $list = [];
    foreach (cloud_catalog() as $key => $image) {
        $list[] = ['key' => $key, 'label' => $image['label'], 'downloaded' => isset($downloaded[$key]), 'pool' => $downloaded[$key]['pool'] ?? null];
    }
    return $list;
}

function api_cloud_create($con) {
    api_require_writable();
    ['errors' => $errors, 'params' => $params] = cloud_create_request($con, api_json_body());
    if ($errors) {
        throw new ApiError(implode(' ', $errors), 400);
    }
    $id = job_create('cloud_create', $params);
    action_log('cloud_create_queued', $params['name'], true, 'job #'.$id.' (api)');
    return ['ok' => true, 'job' => $id];
}

function api_jobs($con) {
    return array_map('api_job_view', job_list(100));
}

function api_job($con, $id) {
    foreach (job_list(1000) as $job) {
        if ((int)$job['id'] === (int)$id) {
            return api_job_view($job);
        }
    }
    throw new ApiError('job not found', 404);
}

function api_job_view(array $job) {
    return [
        'id' => (int)$job['id'], 'type' => $job['type'], 'target' => $job['params']['name'] ?? null,
        'status' => $job['status'], 'progress' => $job['progress'] === null ? null : (int)$job['progress'],
        'message' => $job['message'], 'user' => $job['username'],
        'created_at' => (int)$job['created_at'], 'finished_at' => $job['finished_at'] ? (int)$job['finished_at'] : null,
    ];
}

function api_networks($con) {
    $list = [];
    foreach (libvirt_list_networks($con) ?: [] as $name) {
        $net = libvirt_network_get($con, $name);
        $info = $net ? (libvirt_network_get_information($net) ?: []) : [];
        $list[] = ['name' => $name, 'active' => $net && libvirt_network_get_active($net), 'ip_range' => $info['ip_range'] ?? null];
    }
    return $list;
}

function api_pools($con) {
    $list = [];
    foreach (libvirt_list_storagepools($con) ?: [] as $name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $name);
        $info = libvirt_storagepool_get_info($pool);
        $list[] = ['name' => $name, 'active' => (bool)libvirt_storagepool_is_active($pool),
            'capacity' => (int)$info['capacity'], 'allocation' => (int)$info['allocation'], 'available' => (int)$info['available']];
    }
    return $list;
}
