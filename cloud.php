<?php

require_once 'function.php';
require_permission('operate');

$catalog = cloud_catalog();
$pools = storage_active_pools($con);
$networks = nic_targets_for($con, $connection);
$node_info = libvirt_node_get_info($con);
$max_memory_mb = (int)floor($node_info['memory'] / 1024);
$max_vcpus = (int)$node_info['cpus'];

$saved_keys = ssh_key_list(current_user()['id']);
$form = [
    'image' => '', 'name' => '', 'memory' => 2048, 'vcpus' => min(2, $max_vcpus), 'disk' => 20,
    'pool' => array_key_first($pools) ?? '', 'network' => nic_target_default($networks),
    'user' => 'admin', 'ssh_keys' => '', 'ssh_key_ids' => array_column($saved_keys, 'id'), 'start' => 1,
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
    ['errors' => $errors, 'form' => $form, 'params' => $params] = cloud_create_request($con, $_POST);

    if (!$errors) {
        $id = job_create('cloud_create', $params);
        action_log('cloud_create_queued', $form['name'], true, $form['image'].' (job #'.$id.')');
        flash_set('success', t('Machine %s is being created in the background (job #%d).', $form['name'], $id));
        redirect('jobs.php');
    }
}

$smarty->assign('catalog', $catalog);
// labels of all entries: a disabled image that is already downloaded stays selectable
$smarty->assign('image_labels', array_map(fn($image) => $image['label'], cloud_catalog(true)));
$smarty->assign('downloaded', cloud_downloaded($con));
// downloads in progress: image key => job
$downloading = [];
foreach (array_keys($catalog) as $key) {
    if ($job = job_find_pending('image_download', 'image', $key)) {
        $downloading[$key] = $job;
    }
}
$smarty->assign('downloading', $downloading);
$smarty->assign('pools', $pools);
$smarty->assign('networks', $networks);
$smarty->assign('form', $form);
$smarty->assign('saved_keys', $saved_keys);
$smarty->assign('selected_keys', array_flip($form['ssh_key_ids']));
$smarty->assign('errors', $errors);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->assign('iso_tool', is_executable('/usr/bin/xorriso') || is_executable('/usr/bin/genisoimage'));
$smarty->display('cloud.tpl');
