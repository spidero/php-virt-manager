<?php

// cloud image catalog: edit entries, synchronize from the catalog URL
require_once 'function.php';
require_permission('admin', 'cloud.php');

$form = ['key' => '', 'label' => '', 'url' => '', 'new' => 1];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');
    $key = (string)($_POST['key'] ?? '');
    $user = current_user()['username'];

    if ($action === 'sync') {
        $state = cloud_catalog_sync();
        action_log('cloud_catalog_sync', $state['url'], $state['ok'], $state['message']);
        if ($state['ok']) {
            flash_set('success', t('Catalog synchronized: %d added, %d updated, %d kept.', $state['added'], $state['updated'], $state['kept']));
        }
        else {
            flash_set('danger', t('Synchronization failed: %s', $state['message']));
        }
        redirect('cloud-catalog.php');
    }
    elseif ($action === 'save') {
        $form = ['key' => $key, 'label' => (string)($_POST['label'] ?? ''), 'url' => (string)($_POST['url'] ?? ''),
            'new' => empty($_POST['new']) ? 0 : 1];
        $errors = cloud_image_save($form['key'], $form['label'], $form['url'], (bool)$form['new']);
        if (!$errors) {
            action_log($form['new'] ? 'cloud_image_add' : 'cloud_image_edit', $key, true, trim($form['url']));
            flash_set('success', t('Image %s saved.', $key));
            redirect('cloud-catalog.php');
        }
    }
    elseif (in_array($action, ['enable', 'disable'], true)) {
        if (cloud_image_set_enabled($key, $action === 'enable')) {
            action_log('cloud_image_'.$action, $key, true);
        }
        redirect('cloud-catalog.php');
    }
    elseif ($action === 'reset') {
        if (cloud_image_reset($key)) {
            action_log('cloud_image_reset', $key, true);
            flash_set('success', t('%s will be updated from the list on the next synchronization.', $key));
        }
        redirect('cloud-catalog.php');
    }
    elseif ($action === 'delete') {
        if (cloud_image_delete($key)) {
            action_log('cloud_image_delete', $key, true);
            flash_set('success', t('Image %s removed from the catalog.', $key));
        }
        redirect('cloud-catalog.php');
    }
    else {
        redirect('cloud-catalog.php');
    }
}
elseif (isset($_GET['edit'])) {
    foreach (cloud_catalog_rows() as $row) {
        if ($row['key'] === $_GET['edit']) {
            $form = ['key' => $row['key'], 'label' => $row['label'], 'url' => $row['url'], 'new' => 0];
        }
    }
}

$smarty->assign('rows', cloud_catalog_rows());
$smarty->assign('downloaded', cloud_downloaded($con));
$smarty->assign('form', $form);
$smarty->assign('errors', $errors);
$smarty->assign('catalog_url', cloud_catalog_url());
$smarty->assign('auto_sync', !empty($GLOBALS['cloud_catalog_auto_sync']));
$smarty->assign('last_sync', cloud_catalog_last_sync());
$smarty->display('cloud-catalog.tpl');
