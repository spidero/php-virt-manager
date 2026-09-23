<?php

require_once 'function.php';

// libvirt storage pool states (virStoragePoolState)
$pool_states = [0 => 'inactive', 1 => 'building', 2 => 'running', 3 => 'degraded', 4 => 'inaccessible'];

$pools = libvirt_list_storagepools($con) ?: [];
$storage = (string)($_GET['storage'] ?? $_POST['storage'] ?? '');
if ($storage !== '' && !in_array($storage, $pools, true)) {
    flash_set('danger', t('Unknown storage pool: %s', $storage));
    redirect('storage.php');
}
$back = $storage !== '' ? 'storage.php?storage='.urlencode($storage) : 'storage.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard($back, 'admin');
    $action = (string)($_POST['action'] ?? '');
    $pool = libvirt_storagepool_lookup_by_name($con, $storage);
    $target = $storage;
    $details = '';
    $message = '';

    try {
        if (!$pool) {
            throw new RuntimeException(t('Unknown storage pool: %s', $storage));
        }
        if ($action === 'pool_start') {
            $ok = libvirt_storagepool_create($pool);
            $message = t('Storage pool %s started.', $storage);
        }
        elseif ($action === 'pool_stop') {
            $ok = libvirt_storagepool_destroy($pool);
            $message = t('Storage pool %s stopped.', $storage);
        }
        elseif ($action === 'pool_refresh') {
            $ok = libvirt_storagepool_refresh($pool, 0);
            $message = t('Storage pool %s refreshed.', $storage);
        }
        elseif ($action === 'pool_autostart') {
            $enable = !empty($_POST['enable']);
            $ok = libvirt_storagepool_set_autostart($pool, $enable);
            $details = $enable ? 'on' : 'off';
            $message = $enable ? t('Autostart enabled') : t('Autostart disabled');
        }
        elseif ($action === 'volume_create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $size = (int)($_POST['size'] ?? 0);
            $format = (string)($_POST['format'] ?? 'qcow2');
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $name)) {
                throw new RuntimeException(t('Invalid volume name (letters, digits, . _ - only).'));
            }
            if ($size < 1 || $size > 4096) {
                throw new RuntimeException(t('Disk size must be between %d and %d GB.', 1, 4096));
            }
            if (!in_array($format, ['qcow2', 'raw'], true)) {
                throw new RuntimeException(t('Unknown format.'));
            }
            if (in_array($name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
                throw new RuntimeException(t('Volume %s already exists in pool %s.', $name, $storage));
            }
            $xml = '<volume><name>'.xml_escape($name).'</name>'
                ."<capacity unit='G'>".$size.'</capacity>'
                ."<target><format type='".$format."'/></target></volume>";
            $ok = (bool)libvirt_storagevolume_create_xml($pool, $xml);
            $target = $storage.'/'.$name;
            $details = $size.' GB '.$format;
            $message = t('Volume %s created.', $name);
        }
        elseif ($action === 'volume_delete') {
            $name = (string)($_POST['volume'] ?? '');
            if (!in_array($name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
                throw new RuntimeException(t('Unknown volume: %s', $name));
            }
            $vol = libvirt_storagevolume_lookup_by_name($pool, $name);
            if (in_array((string)libvirt_storagevolume_get_path($vol), storage_sources_in_use($con), true)) {
                throw new RuntimeException(t('Volume %s is used by a machine.', $name));
            }
            $ok = libvirt_storagevolume_delete($vol, 0);
            $target = $storage.'/'.$name;
            $message = t('Volume %s deleted.', $name);
        }
        else {
            throw new RuntimeException(t('Unknown action: %s', $action));
        }
        if (!$ok) {
            throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
        }
        action_log($action, $target, true, $details);
        flash_set('success', $message);
    }
    catch (RuntimeException $e) {
        action_log($action, $target, false, $e->getMessage());
        flash_set('danger', $e->getMessage());
    }
    redirect($back);
}

if ($storage === '') {
    $list = [];
    foreach ($pools as $name) {
        $pool = libvirt_storagepool_lookup_by_name($con, $name);
        $info = libvirt_storagepool_get_info($pool);
        $list[] = [
            'name'       => $name,
            'active'     => (bool)libvirt_storagepool_is_active($pool),
            'autostart'  => (bool)libvirt_storagepool_get_autostart($pool),
            'state'      => $pool_states[$info['state']] ?? 'unknown',
            'capacity'   => format_bytes($info['capacity']),
            'allocation' => format_bytes($info['allocation']),
            'available'  => format_bytes($info['available']),
        ];
    }
    $smarty->assign('pools', $list);
}
else {
    $pool = libvirt_storagepool_lookup_by_name($con, $storage);
    $in_use = storage_sources_in_use($con);
    $active = (bool)libvirt_storagepool_is_active($pool);

    $volumes = [];
    if ($active) {
        foreach (libvirt_storagepool_list_volumes($pool) ?: [] as $name) {
            $vol = libvirt_storagevolume_lookup_by_name($pool, $name);
            $info = libvirt_storagevolume_get_info($vol);
            $path = (string)libvirt_storagevolume_get_path($vol);
            $volumes[] = [
                'name'       => $name,
                'path'       => $path,
                'capacity'   => format_bytes($info['capacity']),
                'allocation' => format_bytes($info['allocation']),
                'in_use'     => in_array($path, $in_use, true),
            ];
        }
    }
    $smarty->assign('pool_active', $active);
    $smarty->assign('pool_autostart', (bool)libvirt_storagepool_get_autostart($pool));
    $smarty->assign('volumes', $volumes);
    $smarty->assign('xml', libvirt_storagepool_get_xml_desc($pool, null));
}

$smarty->assign('storage', $storage);
$smarty->display('storage.tpl');
