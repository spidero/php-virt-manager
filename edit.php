<?php

require_once 'function.php';
require_permission('operate');

// libvirt device modification flags
const AFFECT_LIVE = 1;
const AFFECT_CONFIG = 2;
const AFFECT_LIVE_CONFIG = 3;
// undefine: managed save image, snapshot metadata and UEFI variables file
const UNDEFINE_ALL_METADATA = 1 | 2 | 4;

$node = (string)($_GET['node'] ?? $_POST['node'] ?? '');
if (!in_array($node, libvirt_list_domains($con) ?: [], true)) {
    flash_set('danger', t('Unknown machine: %s', $node));
    redirect('index.php');
}
$edit_url = 'edit.php?node='.urlencode($node);
$res = libvirt_domain_lookup_by_name($con, $node);
$active = (bool)libvirt_domain_is_active($res);
$xml = (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE);

$node_info = libvirt_node_get_info($con);
$max_memory_mb = (int)floor($node_info['memory'] / 1024);
$max_vcpus = (int)$node_info['cpus'];
$isos = storage_iso_list($con);
$pools = storage_active_pools($con);
$networks = libvirt_list_networks($con) ?: [];

// attaches a device: live and persistent for a running machine (falling back to
// the persistent definition only, e.g. when q35 has no free PCIe port), otherwise
// persistent; returns '' when attached live, the "after restart" note otherwise, false on error
function attach_device($con, $res, $xml, $active, $device) {
    if (!$active) {
        return libvirt_domain_define_xml($con, vm_xml_add_device($xml, $device)) ? '' : false;
    }
    if (libvirt_domain_attach_device($res, $device, AFFECT_LIVE_CONFIG)) {
        return '';
    }
    return libvirt_domain_attach_device($res, $device, AFFECT_CONFIG)
        ? ' '.t('It could not be attached to the running machine and will be available after it is shut down and started again.')
        : false;
}

// redefines the machine with the new persistent XML or throws
function define_or_fail($con, $new_xml) {
    if (!libvirt_domain_define_xml($con, $new_xml)) {
        throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');
    readonly_guard($edit_url, $action === 'delete' ? 'admin' : 'operate');
    $after_restart = $active ? ' '.t('Takes effect after the machine is shut down and started again.') : '';
    $details = '';
    $message = '';
    $back = $edit_url;

    try {
        if ($action === 'resources') {
            $memory = (int)($_POST['memory'] ?? 0);
            $vcpus = (int)($_POST['vcpus'] ?? 0);
            if ($memory < 128 || $memory > $max_memory_mb) {
                throw new RuntimeException(t('Memory must be between %d and %d MB.', 128, $max_memory_mb));
            }
            if ($vcpus < 1 || $vcpus > $max_vcpus) {
                throw new RuntimeException(t('vCPUs must be between %d and %d.', 1, $max_vcpus));
            }
            define_or_fail($con, vm_xml_set_resources($xml, $memory, $vcpus));
            $details = $memory.' MB, '.$vcpus.' vCPU';
            $message = t('Memory and vCPUs saved.').$after_restart;
        }
        elseif ($action === 'cdrom') {
            $iso = (string)($_POST['iso'] ?? '');
            if ($iso !== '' && !isset($isos[$iso])) {
                throw new RuntimeException(t('Unknown ISO image.'));
            }
            [$new_xml, $device] = vm_xml_set_cdrom($xml, $iso);
            define_or_fail($con, $new_xml);
            $message = $iso !== '' ? t('ISO image inserted.') : t('CD/DVD ejected.');
            if ($active && $device !== null) {
                // change the medium in the running machine as well
                if (!libvirt_domain_update_device($res, $device, AFFECT_LIVE)) {
                    $message .= ' '.t('Live change failed (%s).', libvirt_get_last_error()).$after_restart;
                }
            }
            elseif ($active) {
                $message = t('CD/DVD drive added.').$after_restart;
            }
            $details = $iso ?: 'eject';
        }
        elseif ($action === 'add_disk') {
            $size = (int)($_POST['size'] ?? 0);
            $pool = (string)($_POST['pool'] ?? '');
            if ($size < 1 || $size > 4096) {
                throw new RuntimeException(t('Disk size must be between %d and %d GB.', 1, 4096));
            }
            if (!isset($pools[$pool])) {
                throw new RuntimeException(t('Select an active storage pool.'));
            }
            $target = vm_next_target($xml, 'vd');
            $vol = storage_create_qcow2($con, $pool, $node.'-'.$target.'.qcow2', $size);
            $device = vm_disk_device_xml((string)libvirt_storagevolume_get_path($vol), $target);
            $note = attach_device($con, $res, $xml, $active, $device);
            if ($note === false) {
                $error = libvirt_get_last_error();
                libvirt_storagevolume_delete($vol, 0);
                throw new RuntimeException(t('Error: %s', $error));
            }
            $details = $target.', '.$size.' GB in '.$pool.($note ? ', after restart' : '');
            $message = t('Disk %s (%d GB) added.', $target, $size).$note;
        }
        elseif ($action === 'add_nic') {
            $network = (string)($_POST['network'] ?? '');
            if (!in_array($network, $networks, true)) {
                throw new RuntimeException(t('Select a network.'));
            }
            $note = attach_device($con, $res, $xml, $active, vm_nic_device_xml($network));
            if ($note === false) {
                throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
            }
            $details = $network.($note ? ', after restart' : '');
            $message = t('Network interface added.').$note;
        }
        elseif ($action === 'boot') {
            $order = array_values(array_filter(array_map('strval', (array)($_POST['boot'] ?? []))));
            if (!$order) {
                throw new RuntimeException(t('Select at least one boot device.'));
            }
            define_or_fail($con, vm_xml_set_boot_order($xml, $order));
            $details = implode(', ', $order);
            $message = t('Boot order saved.').$after_restart;
        }
        elseif ($action === 'clone') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($active) {
                throw new RuntimeException(t('Shut the machine down first.'));
            }
            if (!preg_match(DOMAIN_NAME_PATTERN, $name)) {
                throw new RuntimeException(t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).'));
            }
            if (in_array($name, libvirt_list_domains($con) ?: [], true) || job_pending('clone', 'name', $name)) {
                throw new RuntimeException(t('A machine with this name already exists.'));
            }
            $id = job_create('clone', ['source' => $node, 'name' => $name]);
            action_log('clone_queued', $node, true, $name.' (job #'.$id.')');
            flash_set('success', t('Cloning to %s started in the background (job #%d).', $name, $id));
            redirect('jobs.php');
        }
        elseif ($action === 'delete') {
            if ($active) {
                throw new RuntimeException(t('Shut the machine down first.'));
            }
            $selected = array_map('strval', (array)($_POST['disks'] ?? []));
            $disk_paths = vm_disk_paths($xml);
            $shared = storage_sources_in_use($con, $node);
            if (!libvirt_domain_undefine_flags($res, UNDEFINE_ALL_METADATA)) {
                throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
            }
            $deleted = [];
            $errors = [];
            foreach ($disk_paths as $target => $path) {
                if (!in_array($target, $selected, true) || in_array($path, $shared, true)) {
                    continue;
                }
                $vol = libvirt_storagevolume_lookup_by_path($con, $path);
                if ($vol && libvirt_storagevolume_delete($vol, 0)) {
                    $deleted[] = $path;
                }
                else {
                    $errors[] = $path;
                }
            }
            $details = 'deleted disks: '.($deleted ? implode(', ', $deleted) : '-');
            action_log('delete', $node, !$errors, $details.($errors ? '; failed: '.implode(', ', $errors) : ''));
            flash_set($errors ? 'warning' : 'success', t('Machine %s deleted.', $node)
                .($deleted ? ' '.t('Deleted disks: %d.', count($deleted)) : '')
                .($errors ? ' '.t('Could not delete: %s', implode(', ', $errors)) : ''));
            redirect('index.php');
        }
        else {
            throw new RuntimeException(t('Unknown action: %s', $action));
        }
        action_log('edit_'.$action, $node, true, $details);
        flash_set('success', $message);
    }
    catch (RuntimeException | InvalidArgumentException $e) {
        action_log('edit_'.$action, $node, false, $e->getMessage());
        flash_set('danger', $e->getMessage());
    }
    redirect($back);
}

$doc = simplexml_load_string($xml);
$cdrom = $doc->xpath("/domain/devices/disk[@device='cdrom']")[0] ?? null;
$shared = storage_sources_in_use($con, $node);
$disks = [];
foreach (vm_disk_paths($xml) as $target => $path) {
    $disks[] = ['target' => $target, 'path' => $path, 'shared' => in_array($path, $shared, true)];
}

$smarty->assign('node', $node);
$smarty->assign('active', $active);
$smarty->assign('memory_mb', vm_memory_mb($xml));
$smarty->assign('vcpus', (int)$doc->vcpu);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->assign('cdrom_iso', $cdrom ? (string)($cdrom->source['file'] ?? '') : null);
$smarty->assign('isos', $isos);
$smarty->assign('pools', $pools);
$smarty->assign('networks', $networks);
$smarty->assign('boot', vm_boot_order($xml));
$smarty->assign('disks', $disks);
$smarty->display('edit.tpl');
