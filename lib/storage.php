<?php

// storage helpers shared by the create, edit and clone pages

// active pools: name => "x GB free"
function storage_active_pools($con) {
    $pools = [];
    foreach (libvirt_list_storagepools($con) ?: [] as $name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $name);
        if ($pool && libvirt_storagepool_is_active($pool)) {
            $info = libvirt_storagepool_get_info($pool);
            $pools[$name] = t('%s free', format_bytes($info['available']));
        }
    }
    return $pools;
}

// ISO images in active pools: path => "pool / file"
function storage_iso_list($con) {
    $isos = [];
    foreach (array_keys(storage_active_pools($con)) as $pool_name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $pool_name);
        foreach (libvirt_storagepool_list_volumes($pool) ?: [] as $vol_name) {
            if (preg_match('/\.iso$/i', $vol_name)) {
                $vol = libvirt_storagevolume_lookup_by_name($pool, $vol_name);
                $isos[(string)libvirt_storagevolume_get_path($vol)] = $pool_name.' / '.$vol_name;
            }
        }
    }
    return $isos;
}

// creates an empty qcow2 volume, returns the volume resource or throws
function storage_create_qcow2($con, $pool_name, $vol_name, $size_gb) {
    $pool = libvirt_storagepool_lookup_by_name($con, $pool_name);
    if (!$pool) {
        throw new RuntimeException(t('Unknown storage pool: %s', $pool_name));
    }
    if (in_array($vol_name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
        throw new RuntimeException(t('Volume %s already exists in pool %s.', $vol_name, $pool_name));
    }
    $vol = libvirt_storagevolume_create_xml($pool, volume_new_xml($vol_name, $size_gb));
    if (!$vol) {
        throw new RuntimeException(t('Cannot create disk: %s', libvirt_get_last_error()));
    }
    return $vol;
}

// file sources used by all machines except $except_name
function storage_sources_in_use($con, $except_name = null) {
    $paths = [];
    foreach (libvirt_list_domains($con) ?: [] as $name) {
        if ($name === $except_name) {
            continue;
        }
        $res = libvirt_domain_lookup_by_name($con, $name);
        $xml = $res ? libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE) : '';
        if ($xml) {
            $paths = array_merge($paths, vm_all_sources($xml));
        }
    }
    return array_values(array_unique($paths));
}

// storage pool types offered by the "new pool" form
const STORAGE_POOL_TYPES = ['dir' => 'Directory', 'netfs' => 'NFS share', 'logical' => 'LVM volume group'];
const STORAGE_PATH_PATTERN = '#^/[A-Za-z0-9._/-]*$#';
const STORAGE_HOST_PATTERN = '/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/';

// pool definition from the form; returns [xml, errors]
function storage_pool_xml($type, $name, array $p) {
    $errors = [];
    $path = rtrim(trim((string)($p['path'] ?? '')), '/');
    $host = trim((string)($p['host'] ?? ''));
    $export = trim((string)($p['export'] ?? ''));
    $vg = trim((string)($p['vg'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', (string)$name)) {
        $errors[] = t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).');
    }
    $valid_path = fn($v) => $v !== '' && preg_match(STORAGE_PATH_PATTERN, $v) && !str_contains($v, '..') && !str_contains($v, '//');
    if ($type === 'dir' || $type === 'netfs') {
        if (!$valid_path($path) || in_array($path, ['', '/', '/etc', '/usr', '/bin', '/boot', '/dev', '/proc', '/sys'], true)) {
            $errors[] = t('Target directory: an absolute path such as /srv/vm-images.');
        }
    }
    if ($type === 'netfs') {
        if (!preg_match(STORAGE_HOST_PATTERN, $host)) {
            $errors[] = t('Invalid NFS server.');
        }
        if (!$valid_path($export)) {
            $errors[] = t('NFS export: an absolute path such as /export/vm.');
        }
    }
    if ($type === 'logical' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9+_.-]{0,127}$/', $vg)) {
        $errors[] = t('Invalid volume group name.');
    }
    if (!isset(STORAGE_POOL_TYPES[$type])) {
        $errors[] = t('Unknown pool type.');
    }
    if ($errors) {
        return ['', $errors];
    }
    $name_xml = '<name>'.xml_escape($name).'</name>';
    $xml = match ($type) {
        'dir'     => "<pool type='dir'>".$name_xml.'<target><path>'.xml_escape($path).'</path></target></pool>',
        'netfs'   => "<pool type='netfs'>".$name_xml."<source><host name='".xml_escape($host)."'/>"
            ."<dir path='".xml_escape($export)."'/><format type='nfs'/></source>"
            .'<target><path>'.xml_escape($path).'</path></target></pool>',
        default   => "<pool type='logical'>".$name_xml.'<source><name>'.xml_escape($vg).'</name><format type="lvm2"/></source>'
            .'<target><path>/dev/'.xml_escape($vg).'</path></target></pool>',
    };
    return [$xml, []];
}

// disk paths of running machines (their disks grow through the machine, not the volume)
function storage_sources_in_use_running($con) {
    $paths = [];
    foreach (libvirt_list_active_domains($con) ?: [] as $name) {
        $res = libvirt_domain_lookup_by_name($con, $name);
        $xml = $res ? (string)libvirt_domain_get_xml_desc($res, null, 0) : '';
        if ($xml !== '') {
            $paths = array_merge($paths, vm_all_sources($xml));
        }
    }
    return $paths;
}

// volumes that can be attached as a disk: not ISO images, not used by any machine;
// path => [label, format]
function storage_free_volumes($con) {
    $in_use = storage_sources_in_use($con);
    $list = [];
    foreach (array_keys(storage_active_pools($con)) as $pool_name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $pool_name);
        foreach (libvirt_storagepool_list_volumes($pool) ?: [] as $vol_name) {
            if (preg_match('/\.iso$/i', $vol_name)) {
                continue;
            }
            $vol = libvirt_storagevolume_lookup_by_name($pool, $vol_name);
            $path = $vol ? (string)libvirt_storagevolume_get_path($vol) : '';
            if ($path === '' || in_array($path, $in_use, true)) {
                continue;
            }
            $info = libvirt_storagevolume_get_info($vol);
            $xml = @simplexml_load_string((string)libvirt_storagevolume_get_xml_desc($vol, null));
            $format = $xml ? (string)($xml->target->format['type'] ?? 'raw') : 'raw';
            $list[$path] = ['label' => $pool_name.' / '.$vol_name.' ('.format_bytes($info['capacity']).', '.$format.')', 'format' => $format];
        }
    }
    return $list;
}
