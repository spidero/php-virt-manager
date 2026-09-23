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
