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

// capacity of a disk in bytes (running machine: block info, otherwise the volume), null when unknown
function disk_capacity($con, $res, $active, $target, $path) {
    if ($active) {
        $block = @libvirt_domain_get_block_info($res, $target);
        return is_array($block) ? (int)$block['capacity'] : null;
    }
    $vol = $path !== '' ? @libvirt_storagevolume_lookup_by_path($con, $path) : false;
    $info = $vol ? @libvirt_storagevolume_get_info($vol) : false;
    return is_array($info) ? (int)$info['capacity'] : null;
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
            $current = (int)($_POST['current_memory'] ?? $memory);
            $cpu = [
                'mode'    => (string)($_POST['cpu_mode'] ?? 'host-passthrough'),
                'sockets' => (int)($_POST['sockets'] ?? 1),
                'cores'   => (int)($_POST['cores'] ?? 1),
                'threads' => (int)($_POST['threads'] ?? 1),
            ];
            $vcpus = $cpu['sockets'] * $cpu['cores'] * $cpu['threads'];
            if ($memory < 128 || $memory > $max_memory_mb) {
                throw new RuntimeException(t('Memory must be between %d and %d MB.', 128, $max_memory_mb));
            }
            if ($current < 128 || $current > $memory) {
                throw new RuntimeException(t('Current memory must be between %d MB and the maximum memory.', 128));
            }
            if (min($cpu['sockets'], $cpu['cores'], $cpu['threads']) < 1 || $vcpus > $max_vcpus) {
                throw new RuntimeException(t('vCPUs (sockets x cores x threads) must be between %d and %d.', 1, $max_vcpus));
            }
            $old_max = vm_memory_mb($xml);
            $old_cpu = vm_cpu_settings($xml);
            // the current mode stays allowed (e.g. custom with a named model)
            if (!isset(VM_CPU_MODES[$cpu['mode']]) && $cpu['mode'] !== $old_cpu['mode']) {
                throw new RuntimeException(t('Unknown CPU mode.'));
            }
            $old_vcpus = $old_cpu['sockets'] * $old_cpu['cores'] * $old_cpu['threads'];
            define_or_fail($con, vm_xml_set_resources($xml, $memory, $vcpus, $current, $cpu));
            $details = sprintf('%d/%d MB, %d vCPU (%dx%dx%d), %s', $current, $memory, $vcpus, $cpu['sockets'], $cpu['cores'], $cpu['threads'], $cpu['mode']);
            $message = t('Memory and vCPUs saved.');
            // the balloon target of a running machine changes at once (up to the current maximum)
            $only_balloon = $memory === $old_max && $cpu == $old_cpu && $vcpus === $old_vcpus;
            if ($active && $current !== vm_memory_mb($xml, 'currentMemory') && $current <= $old_max) {
                $message .= libvirt_domain_set_memory_flags($res, $current * 1024, AFFECT_LIVE)
                    ? ' '.t('The running machine now has %d MB.', $current)
                    : ' '.t('Live change failed (%s).', libvirt_get_last_error());
            }
            if ($active && !$only_balloon) {
                $message .= $after_restart;
            }
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
        elseif ($action === 'detach_disk') {
            $target = (string)($_POST['target'] ?? '');
            $disks = vm_disk_devices($xml);
            if (!isset($disks[$target]) || $disks[$target]['device'] !== 'disk') {
                throw new RuntimeException(t('Unknown disk.'));
            }
            $delete = !empty($_POST['delete_volume']);
            if ($delete && ($active || !can('admin'))) {
                throw new RuntimeException(t('The disk file can be deleted only by an administrator, with the machine shut down.'));
            }
            $path = $disks[$target]['path'];
            if ($delete && in_array($path, storage_sources_in_use($con, $node), true)) {
                throw new RuntimeException(t('The disk is used by another machine and cannot be deleted.'));
            }
            if ($active) {
                // the guest releases the disk (hot-unplug), the definition changes at once
                if (!libvirt_domain_detach_device($res, $disks[$target]['xml'], AFFECT_LIVE_CONFIG)) {
                    throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                }
            }
            else {
                define_or_fail($con, vm_xml_remove_disk($xml, $target));
            }
            $message = t('Disk %s detached.', $target);
            $details = $target.' '.$path;
            if ($delete) {
                $vol = libvirt_storagevolume_lookup_by_path($con, $path);
                if ($vol && libvirt_storagevolume_delete($vol, 0)) {
                    $message .= ' '.t('The disk file was deleted.');
                    $details .= ', file deleted';
                }
                else {
                    $message .= ' '.t('Could not delete: %s', $path);
                }
            }
        }
        elseif ($action === 'resize_disk') {
            $target = (string)($_POST['target'] ?? '');
            $size_gb = (int)($_POST['size'] ?? 0);
            $disks = vm_disk_devices($xml);
            if (!isset($disks[$target]) || $disks[$target]['device'] !== 'disk') {
                throw new RuntimeException(t('Unknown disk.'));
            }
            $bytes = $size_gb * 1073741824;
            $current = disk_capacity($con, $res, $active, $target, $disks[$target]['path']);
            if ($size_gb < 1 || $size_gb > 65536) {
                throw new RuntimeException(t('Disk size must be between %d and %d GB.', 1, 65536));
            }
            if ($current !== null && $bytes <= $current) {
                throw new RuntimeException(t('The new size must be larger than the current %s (shrinking would destroy data).', format_bytes($current)));
            }
            if ($active) {
                // flag 1 = size in bytes; the guest sees the bigger disk at once
                $ok = libvirt_domain_block_resize($res, $target, $bytes, 1);
            }
            else {
                $vol = libvirt_storagevolume_lookup_by_path($con, $disks[$target]['path']);
                $ok = $vol && libvirt_storagevolume_resize($vol, $bytes, 0) !== false;
            }
            if (!$ok) {
                throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
            }
            $details = $target.' -> '.$size_gb.' GB';
            $message = t('Disk %s resized to %d GB. Grow the partition and file system in the guest (e.g. growpart, resize2fs); cloud images do it at the next boot.', $target, $size_gb);
        }
        elseif ($action === 'detach_nic') {
            $mac = strtolower((string)($_POST['mac'] ?? ''));
            $nics = vm_nic_devices($xml);
            if (!isset($nics[$mac])) {
                throw new RuntimeException(t('Unknown network interface.'));
            }
            if ($active) {
                if (!libvirt_domain_detach_device($res, $nics[$mac]['xml'], AFFECT_LIVE_CONFIG)) {
                    throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                }
            }
            else {
                define_or_fail($con, vm_xml_remove_nic($xml, $mac));
            }
            $details = $mac;
            $message = t('Network interface %s detached.', $mac);
        }
        elseif ($action === 'nic_change') {
            $mac = strtolower((string)($_POST['mac'] ?? ''));
            $network = (string)($_POST['network'] ?? '');
            $model = (string)($_POST['model'] ?? '');
            $nics = vm_nic_devices($xml);
            if (!isset($nics[$mac])) {
                throw new RuntimeException(t('Unknown network interface.'));
            }
            if (!in_array($network, $networks, true)) {
                throw new RuntimeException(t('Select a network.'));
            }
            if (!isset(NIC_MODELS[$model])) {
                throw new RuntimeException(t('Unknown network card model.'));
            }
            $new_nic = vm_nic_xml_change($nics[$mac]['xml'], $network, $model);
            $model_changed = $model !== $nics[$mac]['model'];
            if ($active && !$model_changed) {
                // network switch of the running machine (link stays, new network at once)
                if (!libvirt_domain_update_device($res, $new_nic, AFFECT_LIVE_CONFIG)) {
                    throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                }
                $message = t('Network interface %s connected to %s.', $mac, $network);
            }
            else {
                define_or_fail($con, vm_xml_replace_nic($xml, $mac, $new_nic));
                $message = t('Network interface %s saved.', $mac).($model_changed ? $after_restart : '');
            }
            $details = $mac.': '.$network.', '.$model;
        }
        elseif ($action === 'serial_console') {
            $enable = !empty($_POST['enable']);
            $port = $enable ? (vm_serial_port($xml) ?: console_serial_free_port($con)) : 0;
            define_or_fail($con, vm_xml_set_serial($xml, $port));
            $details = $enable ? 'tcp 127.0.0.1:'.$port : 'pty';
            $message = ($enable ? t('Text console enabled.') : t('Text console disabled.')).$after_restart;
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
        elseif ($action === 'rename') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($active) {
                throw new RuntimeException(t('Shut the machine down first.'));
            }
            if (!preg_match(DOMAIN_NAME_PATTERN, $name)) {
                throw new RuntimeException(t('Name: 1-64 characters, letters, digits, . _ - (must start with a letter or digit).'));
            }
            if (in_array($name, libvirt_list_domains($con) ?: [], true)) {
                throw new RuntimeException(t('A machine with this name already exists.'));
            }
            [$status, $output] = domain_virsh($connection, ['domrename', $node, $name]);
            if ($status !== 0) {
                throw new RuntimeException(t('Error: %s', trim($output)));
            }
            domain_rename_references(connection_current_key(), $node, $name);
            action_log('edit_rename', $node, true, $name);
            flash_set('success', t('Machine renamed to %s. Disk files keep their names.', $name));
            redirect('edit.php?node='.urlencode($name));
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
            $disk_paths = vm_disk_paths($xml) + vm_seed_paths($xml);
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
foreach (vm_disk_paths($xml) + vm_seed_paths($xml) as $target => $path) {
    $disks[] = ['target' => $target, 'path' => $path, 'shared' => in_array($path, $shared, true)];
}

$smarty->assign('node', $node);
$smarty->assign('active', $active);
$smarty->assign('memory_mb', vm_memory_mb($xml));
$smarty->assign('current_memory_mb', vm_memory_mb($xml, 'currentMemory'));
$smarty->assign('cpu', vm_cpu_settings($xml));
$smarty->assign('cpu_modes', VM_CPU_MODES);
$smarty->assign('vcpus', (int)$doc->vcpu);
$smarty->assign('max_memory_mb', $max_memory_mb);
$smarty->assign('max_vcpus', $max_vcpus);
$smarty->assign('cdrom_iso', $cdrom ? (string)($cdrom->source['file'] ?? '') : null);
$smarty->assign('isos', $isos);
$smarty->assign('pools', $pools);
$smarty->assign('networks', $networks);
$smarty->assign('boot', vm_boot_order($xml));
$smarty->assign('disks', $disks);
// devices for the disk and network interface tables
$device_disks = [];
foreach (vm_disk_devices($xml) as $target => $d) {
    if ($d['device'] !== 'disk') {
        continue;
    }
    $capacity = disk_capacity($con, $res, $active, $target, $d['path']);
    $device_disks[] = $d + ['target' => $target, 'capacity' => $capacity !== null ? format_bytes($capacity) : '-',
        'capacity_gb' => $capacity !== null ? (int)ceil($capacity / 1073741824) : 0,
        'shared' => in_array($d['path'], $shared, true)];
}
$smarty->assign('device_disks', $device_disks);
$smarty->assign('device_nics', vm_nic_devices($xml));
$smarty->assign('nic_models', NIC_MODELS);
$smarty->assign('serial_port', vm_serial_port($xml));
$smarty->display('edit.tpl');
