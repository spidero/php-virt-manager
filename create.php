<?php

require_once 'function.php';

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
$networks = libvirt_list_networks($con) ?: [];

$form = [
    'name'    => '',
    'memory'  => 2048,
    'vcpus'   => min(2, $max_vcpus),
    'disk'    => 20,
    'pool'    => array_key_first($pools) ?? '',
    'iso'     => '',
    'network' => in_array('default', $networks, true) ? 'default' : ($networks[0] ?? ''),
    'start'   => 1,
];
$errors = [];
$pool = $vol = $res = null;
$vol_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    readonly_guard('create.php');

    $form = [
        'name'    => trim((string)($_POST['name'] ?? '')),
        'memory'  => (int)($_POST['memory'] ?? 0),
        'vcpus'   => (int)($_POST['vcpus'] ?? 0),
        'disk'    => (int)($_POST['disk'] ?? 0),
        'pool'    => (string)($_POST['pool'] ?? ''),
        'iso'     => (string)($_POST['iso'] ?? ''),
        'network' => (string)($_POST['network'] ?? ''),
        'start'   => empty($_POST['start']) ? 0 : 1,
    ];

    if (!preg_match(DOMAIN_NAME_PATTERN, $form['name'])) {
        $errors[] = 'Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).';
    }
    elseif (in_array($form['name'], libvirt_list_domains($con) ?: [], true)) {
        $errors[] = 'A machine with this name already exists.';
    }
    if ($form['memory'] < 256 || $form['memory'] > $max_memory_mb) {
        $errors[] = 'Memory must be between 256 and '.$max_memory_mb.' MB.';
    }
    if ($form['vcpus'] < 1 || $form['vcpus'] > $max_vcpus) {
        $errors[] = 'vCPUs must be between 1 and '.$max_vcpus.'.';
    }
    if ($form['disk'] < 1 || $form['disk'] > 4096) {
        $errors[] = 'Disk size must be between 1 and 4096 GB.';
    }
    if (!isset($pools[$form['pool']])) {
        $errors[] = 'Select an active storage pool.';
    }
    if ($form['iso'] !== '' && !isset($isos[$form['iso']])) {
        $errors[] = 'Unknown ISO image.';
    }
    if (!in_array($form['network'], $networks, true)) {
        $errors[] = 'Select a network.';
    }

    if (!$errors) {
        $pool = libvirt_storagepool_lookup_by_name($con, $form['pool']);
        $vol_name = $form['name'].'.qcow2';
        if (in_array($vol_name, libvirt_storagepool_list_volumes($pool) ?: [], true)) {
            $errors[] = 'Volume '.$vol_name.' already exists in pool '.$form['pool'].'.';
        }
    }

    if (!$errors) {
        $vol = libvirt_storagevolume_create_xml($pool, volume_new_xml($vol_name, $form['disk']));
        if (!$vol) {
            $errors[] = 'Cannot create disk: '.libvirt_get_last_error();
        }
    }

    if (!$errors) {
        $xml = domain_new_xml($form['name'], $form['memory'], $form['vcpus'],
            (string)libvirt_storagevolume_get_path($vol), $form['iso'], $form['network']);
        $res = libvirt_domain_define_xml($con, $xml);
        if (!$res) {
            $errors[] = 'Cannot define machine: '.libvirt_get_last_error();
            // do not leave an orphaned disk behind
            libvirt_storagevolume_delete($vol, 0);
        }
    }

    $details = sprintf('%d MB, %d vCPU, %d GB in %s, iso: %s, net: %s',
        $form['memory'], $form['vcpus'], $form['disk'], $form['pool'], $form['iso'] ?: '-', $form['network']);
    if ($errors) {
        action_log('create', $form['name'], false, $details.'; '.implode(' ', $errors));
    }
    else {
        action_log('create', $form['name'], true, $details);
        $message = 'Machine '.$form['name'].' created';
        if ($form['start']) {
            if (libvirt_domain_create($res)) {
                action_log('start', $form['name'], true);
                $message .= ' and started';
            }
            else {
                $message .= ', but start failed: '.libvirt_get_last_error();
            }
        }
        flash_set('success', $message);
        redirect('node.php?node='.urlencode($form['name']));
    }
}

$smarty->assign('form', $form);
$smarty->assign('errors', $errors);
$smarty->assign('pools', $pools);
$smarty->assign('isos', $isos);
$smarty->assign('networks', $networks);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->display('create.tpl');
