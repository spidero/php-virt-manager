<?php

require_once 'function.php';

// libvirt storage pool states (virStoragePoolState)
$pool_states = [0 => 'inactive', 1 => 'building', 2 => 'running', 3 => 'degraded', 4 => 'inaccessible'];

$pools = libvirt_list_storagepools($con) ?: [];
$storage = (string)($_GET['storage'] ?? '');

if ($storage === '') {
    $list = [];
    foreach ($pools as $name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $name);
        $info = libvirt_storagepool_get_info($pool);
        $list[] = [
            'name'       => $name,
            'state'      => $pool_states[$info['state']] ?? $info['state'],
            'capacity'   => format_bytes($info['capacity']),
            'allocation' => format_bytes($info['allocation']),
            'available'  => format_bytes($info['available']),
        ];
    }
    $smarty->assign('pools', $list);
}
else {
    if (!in_array($storage, $pools, true)) {
        flash_set('danger', t('Unknown storage pool: %s', $storage));
        redirect('storage.php');
    }
    $pool = libvirt_storagepool_lookup_by_name($con, $storage);

    $volumes = [];
    if (libvirt_storagepool_is_active($pool)) {
        foreach (libvirt_storagepool_list_volumes($pool) ?: [] as $name) {
            $vol = libvirt_storagevolume_lookup_by_name($pool, $name);
            $info = libvirt_storagevolume_get_info($vol);
            $volumes[] = [
                'name'       => $name,
                'path'       => libvirt_storagevolume_get_path($vol),
                'capacity'   => format_bytes($info['capacity']),
                'allocation' => format_bytes($info['allocation']),
            ];
        }
    }
    $smarty->assign('volumes', $volumes);
    $smarty->assign('xml', libvirt_storagepool_get_xml_desc($pool, null));
}

$smarty->assign('storage', $storage);
$smarty->display('storage.tpl');
