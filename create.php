<?php

require_once 'function.php';
require_permission('create');

$node_info = libvirt_node_get_info($con);
$max_memory_mb = (int)floor($node_info['memory'] / 1024);
$max_vcpus = (int)$node_info['cpus'];

// active pools and ISO images found in them
$pools = [];
$isos = [];
foreach (libvirt_list_storagepools($con) ?: [] as $pool_name) {
    $pool = libvirt_storagepool_lookup_by_name($con, $pool_name);
    if (!$pool || !libvirt_storagepool_is_active($pool)) {
        continue;
    }
    $info = libvirt_storagepool_get_info($pool);
    $pools[$pool_name] = format_bytes($info['available']).' free';
    foreach (libvirt_storagepool_list_volumes($pool) ?: [] as $vol_name) {
        if (preg_match('/\.iso$/i', $vol_name)) {
            $vol = libvirt_storagevolume_lookup_by_name($pool, $vol_name);
            $isos[(string)libvirt_storagevolume_get_path($vol)] = $pool_name.' / '.$vol_name;
        }
    }
}
$networks = nic_targets_for($con, $connection);

$form = [
    'name'    => '',
    'memory'  => 2048,
    'vcpus'   => min(2, $max_vcpus),
    'disk'    => 20,
    'pool'    => array_key_first($pools) ?? '',
    'iso'     => '',
    'network' => nic_target_default($networks),
    'os'      => 'linux',
    'drivers' => '',
    'source'  => 'iso',
    'volume'  => '',
    'firmware' => 'bios',
    'tpm'     => 0,
    'start'   => 1,
];
$errors = [];
$pool = $vol = $res = null;
$vol_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard('create.php', 'create');

    $form = [
        'name'    => trim((string)($_POST['name'] ?? '')),
        'memory'  => (int)($_POST['memory'] ?? 0),
        'vcpus'   => (int)($_POST['vcpus'] ?? 0),
        'disk'    => (int)($_POST['disk'] ?? 0),
        'pool'    => (string)($_POST['pool'] ?? ''),
        'iso'     => (string)($_POST['iso'] ?? ''),
        'network' => nic_target_normalize((string)($_POST['network'] ?? '')),
        'os'      => (string)($_POST['os'] ?? 'linux'),
        'drivers' => (string)($_POST['drivers'] ?? ''),
        'source'  => (string)($_POST['source'] ?? 'iso'),
        'volume'  => (string)($_POST['volume'] ?? ''),
        'firmware' => (string)($_POST['firmware'] ?? 'bios'),
        'tpm'     => empty($_POST['tpm']) ? 0 : 1,
        'start'   => empty($_POST['start']) ? 0 : 1,
    ];

    if (!preg_match(DOMAIN_NAME_PATTERN, $form['name'])) {
        $errors[] = t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).');
    }
    elseif (in_array($form['name'], libvirt_list_domains($con) ?: [], true)) {
        $errors[] = t('A machine with this name already exists.');
    }
    if ($form['memory'] < 256 || $form['memory'] > $max_memory_mb) {
        $errors[] = t('Memory must be between %d and %d MB.', 256, $max_memory_mb);
    }
    if ($form['vcpus'] < 1 || $form['vcpus'] > $max_vcpus) {
        $errors[] = t('vCPUs must be between %d and %d.', 1, $max_vcpus);
    }
    if ($form['disk'] < 1 || $form['disk'] > 4096) {
        $errors[] = t('Disk size must be between %d and %d GB.', 1, 4096);
    }
    if (!isset($pools[$form['pool']])) {
        $errors[] = t('Select an active storage pool.');
    }
    if ($form['iso'] !== '' && !isset($isos[$form['iso']])) {
        $errors[] = t('Unknown ISO image.');
    }
    if (!nic_target_valid($form['network'], $networks)) {
        $errors[] = t('Select a network.');
    }
    if (!isset(DOMAIN_FIRMWARES[$form['firmware']])) {
        $errors[] = t('Select the firmware.');
    }
    if (!isset(DOMAIN_OS_PROFILES[$form['os']])) {
        $errors[] = t('Select the operating system.');
    }
    if ($form['drivers'] !== '' && !isset($isos[$form['drivers']])) {
        $errors[] = t('Unknown ISO image.');
    }
    // Windows: with the virtio-win drivers ISO the disk and NIC use virtio (the driver is
    // loaded in the installer), without it SATA and e1000e work out of the box
    $windows = !empty(DOMAIN_OS_PROFILES[$form['os']]['windows']);
    $with_drivers = $windows && $form['drivers'] !== '';
    $os_opts = [
        'windows'     => $windows,
        'disk_bus'    => $windows && !$with_drivers ? 'sata' : 'virtio',
        'nic_model'   => $windows && !$with_drivers ? 'e1000e' : 'virtio',
        'drivers_iso' => $with_drivers ? $form['drivers'] : '',
    ];
    // installation source: ISO (CD/DVD), an imported existing disk or network boot (PXE)
    $free_volumes = storage_free_volumes($con);
    if (!in_array($form['source'], ['iso', 'import', 'pxe'], true)) {
        $errors[] = t('Select the installation source.');
    }
    elseif ($form['source'] === 'import' && !isset($free_volumes[$form['volume']])) {
        $errors[] = t('Select a volume that is not used by a machine.');
    }
    $import = $form['source'] === 'import';
    $iso_path = $form['source'] === 'iso' ? $form['iso'] : '';
    $boot = ['iso' => ['cdrom', 'hd'], 'import' => ['hd'], 'pxe' => ['network', 'hd']][$form['source']] ?? ['hd'];

    if (!$errors) {
        $disk_gb = $import ? quota_volume_gb($con, $form['volume']) : $form['disk'];
        if (($quota = quota_check($con, ['machines' => 1, 'vcpus' => $form['vcpus'], 'memory_mb' => $form['memory'], 'disk_gb' => $disk_gb])) !== '') {
            $errors[] = $quota;
        }
    }

    if (!$errors && !$import) {
        $pool = libvirt_storagepool_lookup_by_name($con, $form['pool']);
        $vol_name = $form['name'].'.qcow2';
        if (in_array($vol_name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
            $errors[] = t('Volume %s already exists in pool %s.', $vol_name, $form['pool']);
        }
    }

    if (!$errors && !$import) {
        $vol = libvirt_storagevolume_create_xml($pool, volume_new_xml($vol_name, $form['disk']));
        if (!$vol) {
            $errors[] = t('Cannot create disk: %s', libvirt_get_last_error());
        }
    }

    if (!$errors) {
        $disk_path = $import ? $form['volume'] : (string)libvirt_storagevolume_get_path($vol);
        $xml = domain_new_xml($form['name'], $form['memory'], $form['vcpus'],
            $disk_path, $iso_path, $form['network'], $boot, 'sata', 0,
            $form['firmware'], (bool)$form['tpm'], $import ? $free_volumes[$form['volume']]['format'] : 'qcow2', $os_opts);
        $res = libvirt_domain_define_xml($con, $xml);
        if (!$res) {
            $errors[] = t('Cannot define machine: %s', libvirt_get_last_error());
            // do not leave an orphaned disk behind (an imported volume stays)
            if (!$import) {
                libvirt_storagevolume_delete($vol, 0);
            }
        }
    }

    $details = sprintf('%s, %d MB, %d vCPU, %s, %s: %s, net: %s, %s%s', $form['os'],
        $form['memory'], $form['vcpus'], $import ? 'imported disk' : $form['disk'].' GB in '.$form['pool'],
        $form['source'], $import ? $form['volume'] : ($iso_path ?: '-'), $form['network'],
        $form['firmware'], $form['tpm'] ? ', tpm' : '');
    if ($errors) {
        action_log('create', $form['name'], false, $details.'; '.implode(' ', $errors));
    }
    else {
        owner_assign_new(owner_job_params(), $form['name']);
        action_log('create', $form['name'], true, $details);
        $message = t('Machine %s created', $form['name']);
        if ($form['start']) {
            if (libvirt_domain_create($res)) {
                action_log('start', $form['name'], true);
                $message .= t(' and started');
            }
            else {
                $message .= t(', but start failed: %s', libvirt_get_last_error());
            }
        }
        flash_set('success', $message);
        redirect('node.php?node='.urlencode($form['name']));
    }
}

$smarty->assign('form', $form);
$smarty->assign('firmwares', DOMAIN_FIRMWARES);
$smarty->assign('os_profiles', DOMAIN_OS_PROFILES);
$smarty->assign('virtio_win_url', VIRTIO_WIN_ISO_URL);
$smarty->assign('free_volumes', storage_free_volumes($con));
$smarty->assign('errors', $errors);
$smarty->assign('pools', $pools);
$smarty->assign('isos', $isos);
$smarty->assign('networks', $networks);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->display('create.tpl');
