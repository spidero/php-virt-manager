<?php

require_once 'function.php';
require_permission('operate');

$catalog = cloud_catalog();
$pools = storage_active_pools($con);
$networks = libvirt_list_networks($con) ?: [];
$node_info = libvirt_node_get_info($con);
$max_memory_mb = (int)floor($node_info['memory'] / 1024);
$max_vcpus = (int)$node_info['cpus'];

$form = [
    'image' => '', 'name' => '', 'memory' => 2048, 'vcpus' => min(2, $max_vcpus), 'disk' => 20,
    'pool' => array_key_first($pools) ?? '', 'network' => in_array('default', $networks, true) ? 'default' : ($networks[0] ?? ''),
    'user' => 'admin', 'ssh_keys' => '', 'start' => 1,
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'download') {
        readonly_guard('cloud.php', 'admin');
        $key = (string)($_POST['image'] ?? '');
        $pool = (string)($_POST['pool'] ?? '');
        if (!isset($catalog[$key]) || !isset($pools[$pool])) {
            flash_set('danger', t('Select an image and an active storage pool.'));
        }
        elseif (isset(cloud_downloaded($con)[$key]) || job_pending('image_download', 'image', $key)) {
            flash_set('warning', t('The image is already downloaded or being downloaded.'));
        }
        else {
            $id = job_create('image_download', ['image' => $key, 'pool' => $pool, 'name' => $catalog[$key]['label']]);
            action_log('image_download_queued', $key, true, $pool.' (job #'.$id.')');
            flash_set('success', t('Download of %s started in the background (job #%d).', $catalog[$key]['label'], $id));
            redirect('jobs.php');
        }
        redirect('cloud.php');
    }

    readonly_guard('cloud.php');
    $downloaded = cloud_downloaded($con);
    $form = [
        'image'    => (string)($_POST['image'] ?? ''),
        'name'     => trim((string)($_POST['name'] ?? '')),
        'memory'   => (int)($_POST['memory'] ?? 0),
        'vcpus'    => (int)($_POST['vcpus'] ?? 0),
        'disk'     => (int)($_POST['disk'] ?? 0),
        'pool'     => (string)($_POST['pool'] ?? ''),
        'network'  => (string)($_POST['network'] ?? ''),
        'user'     => trim((string)($_POST['user'] ?? '')),
        'ssh_keys' => (string)($_POST['ssh_keys'] ?? ''),
        'start'    => empty($_POST['start']) ? 0 : 1,
    ];
    $password = (string)($_POST['password'] ?? '');
    $keys = cloud_ssh_keys($form['ssh_keys']);

    if (!isset($downloaded[$form['image']])) {
        $errors[] = t('Select a downloaded image.');
    }
    if (!preg_match(DOMAIN_NAME_PATTERN, $form['name'])) {
        $errors[] = t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).');
    }
    elseif (in_array($form['name'], libvirt_list_domains($con) ?: [], true) || job_pending('cloud_create', 'name', $form['name'])) {
        $errors[] = t('A machine with this name already exists.');
    }
    if ($form['memory'] < 256 || $form['memory'] > $max_memory_mb) {
        $errors[] = t('Memory must be between %d and %d MB.', 256, $max_memory_mb);
    }
    if ($form['vcpus'] < 1 || $form['vcpus'] > $max_vcpus) {
        $errors[] = t('vCPUs must be between %d and %d.', 1, $max_vcpus);
    }
    if ($form['disk'] < 3 || $form['disk'] > 4096) {
        $errors[] = t('Disk size must be between %d and %d GB.', 3, 4096);
    }
    if (!isset($pools[$form['pool']])) {
        $errors[] = t('Select an active storage pool.');
    }
    if (!in_array($form['network'], $networks, true)) {
        $errors[] = t('Select a network.');
    }
    if (!preg_match(CLOUD_USER_PATTERN, $form['user'])) {
        $errors[] = t('Invalid user name (lowercase letters, digits, _ -).');
    }
    if (trim($form['ssh_keys']) !== '' && count($keys) !== count(array_filter(array_map('trim', preg_split('/\R/', $form['ssh_keys']))))) {
        $errors[] = t('Some SSH keys are not valid public keys.');
    }
    if (!$keys && $password === '') {
        $errors[] = t('Enter an SSH key or a password, otherwise you cannot log in.');
    }
    if ($password !== '' && ($error = user_validate_password($password))) {
        $errors[] = $error;
    }

    if (!$errors) {
        $params = [
            'name' => $form['name'], 'image' => $downloaded[$form['image']]['path'], 'pool' => $form['pool'],
            'memory' => $form['memory'], 'vcpus' => $form['vcpus'], 'disk' => $form['disk'], 'network' => $form['network'],
            'user' => $form['user'], 'ssh_keys' => $keys,
            'password_hash' => $password !== '' ? cloud_password_hash($password) : '', 'start' => $form['start'],
        ];
        $id = job_create('cloud_create', $params);
        action_log('cloud_create_queued', $form['name'], true, $form['image'].' (job #'.$id.')');
        flash_set('success', t('Machine %s is being created in the background (job #%d).', $form['name'], $id));
        redirect('jobs.php');
    }
}

$smarty->assign('catalog', $catalog);
$smarty->assign('downloaded', cloud_downloaded($con));
$smarty->assign('pools', $pools);
$smarty->assign('networks', $networks);
$smarty->assign('form', $form);
$smarty->assign('errors', $errors);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->assign('iso_tool', is_executable('/usr/bin/xorriso') || is_executable('/usr/bin/genisoimage'));
$smarty->display('cloud.tpl');
