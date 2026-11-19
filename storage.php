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
        if ($action === 'pool_create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $type = (string)($_POST['type'] ?? 'dir');
            if (in_array($name, $pools, true)) {
                throw new RuntimeException(t('A storage pool with this name already exists.'));
            }
            if (!isset(storage_supported_pool_types($connection)[$type])) {
                throw new RuntimeException(t('This pool type is not supported by the hypervisor.'));
            }
            [$xml, $errors] = storage_pool_xml($type, $name, $_POST);
            if ($errors) {
                throw new RuntimeException(implode(' ', $errors));
            }
            $new = libvirt_storagepool_define_xml($con, $xml, 0);
            if (!$new) {
                throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
            }
            // dir: creates the directory; netfs: the mount point; logical: nothing to do for an existing VG
            if (in_array($type, STORAGE_BUILD_TYPES, true)) {
                @libvirt_storagepool_build($new);
            }
            if (!libvirt_storagepool_create($new)) {
                $error = libvirt_get_last_error();
                // remove what build created (the empty directory or mount point), then the definition
                if (in_array($type, STORAGE_BUILD_TYPES, true)) {
                    @libvirt_storagepool_delete($new);
                }
                libvirt_storagepool_undefine($new);
                throw new RuntimeException(t('The pool could not be started, it was not added: %s', $error));
            }
            if (!empty($_POST['autostart'])) {
                libvirt_storagepool_set_autostart($new, true);
            }
            action_log('pool_create', $name, true, $type);
            flash_set('success', t('Storage pool %s created.', $name));
            redirect('storage.php?storage='.urlencode($name));
        }
        if (!$pool) {
            throw new RuntimeException(t('Unknown storage pool: %s', $storage));
        }
        if ($action === 'pool_delete') {
            $pool_paths = [];
            $pool_active = (bool)libvirt_storagepool_is_active($pool);
            foreach ($pool_active ? (libvirt_storagepool_list_volumes($pool) ?: []) : [] as $vol_name) {
                $pool_paths[] = (string)libvirt_storagevolume_get_path(libvirt_storagevolume_lookup_by_name($pool, $vol_name));
            }
            if (array_intersect($pool_paths, storage_sources_in_use($con))) {
                throw new RuntimeException(t('Volumes of %s are used by machines.', $storage));
            }
            // only the definition goes away, the files (directory, NFS share, LVM volumes) stay
            $ok = (!$pool_active || libvirt_storagepool_destroy($pool)) && libvirt_storagepool_undefine($pool);
            $message = t('Storage pool %s removed; its files were kept.', $storage);
            $back = 'storage.php';
        }
        elseif ($action === 'volume_resize') {
            $name = (string)($_POST['volume'] ?? '');
            $size = (int)($_POST['size'] ?? 0);
            if (!in_array($name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
                throw new RuntimeException(t('Unknown volume: %s', $name));
            }
            $vol = libvirt_storagevolume_lookup_by_name($pool, $name);
            $info = libvirt_storagevolume_get_info($vol);
            if ($size < 1 || $size > 65536 || $size * 1073741824 <= (int)$info['capacity']) {
                throw new RuntimeException(t('The new size must be larger than the current %s (shrinking would destroy data).', format_bytes($info['capacity'])));
            }
            // a disk of a running machine must grow through the machine (block resize)
            if (in_array((string)libvirt_storagevolume_get_path($vol), storage_sources_in_use_running($con), true)) {
                throw new RuntimeException(t('The volume is a disk of a running machine - resize it on the machine edit page.'));
            }
            $ok = libvirt_storagevolume_resize($vol, $size * 1073741824, 0) !== false;
            $target = $storage.'/'.$name;
            $details = $size.' GB';
            $message = t('Volume %s resized to %d GB.', $name, $size);
        }
        elseif ($action === 'iso_download') {
            $url = trim((string)($_POST['url'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                $name = basename((string)parse_url($url, PHP_URL_PATH));
            }
            if (!preg_match('#^https?://[^\s/]+/\S+$#', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException(t('Enter an http:// or https:// address of the ISO image.'));
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}\.iso$/i', $name)) {
                throw new RuntimeException(t('The volume name must end with .iso (letters, digits, . _ -).'));
            }
            if (in_array($name, libvirt_storagepool_list_volumes($pool) ?: [], true) || job_pending('iso_download', 'name', $name)) {
                throw new RuntimeException(t('Volume %s already exists in pool %s.', $name, $storage));
            }
            $id = job_create('iso_download', ['url' => $url, 'pool' => $storage, 'name' => $name]);
            action_log('iso_download_queued', $storage.'/'.$name, true, $url.' (job #'.$id.')');
            flash_set('success', t('Download of %s started in the background (job #%d).', $name, $id));
            redirect('jobs.php');
        }
        elseif ($action === 'volume_clone') {
            $name = (string)($_POST['volume'] ?? '');
            $new_name = trim((string)($_POST['name'] ?? ''));
            $existing = libvirt_storagepool_list_volumes($pool) ?: [];
            if (!in_array($name, $existing, true)) {
                throw new RuntimeException(t('Unknown volume: %s', $name));
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $new_name)) {
                throw new RuntimeException(t('Invalid volume name (letters, digits, . _ - only).'));
            }
            if (in_array($new_name, $existing, true) || job_pending('volume_clone', 'name', $new_name)) {
                throw new RuntimeException(t('Volume %s already exists in pool %s.', $new_name, $storage));
            }
            $id = job_create('volume_clone', ['pool' => $storage, 'source' => $name, 'name' => $new_name]);
            action_log('volume_clone_queued', $storage.'/'.$name, true, $new_name.' (job #'.$id.')');
            flash_set('success', t('Copy of %s to %s started in the background (job #%d).', $name, $new_name, $id));
            redirect('jobs.php');
        }
        elseif ($action === 'pool_start') {
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
    $smarty->assign('pool_types', storage_supported_pool_types($connection));
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
                'capacity_gb' => (int)ceil($info['capacity'] / 1073741824),
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
