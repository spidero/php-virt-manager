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
$xml = domain_definition_xml($res);

$node_info = libvirt_node_get_info($con);
$max_memory_mb = (int)floor($node_info['memory'] / 1024);
$max_vcpus = (int)$node_info['cpus'];
$isos = storage_iso_list($con);
$pools = storage_active_pools($con);
// interface targets: libvirt networks, host bridges, direct (macvtap) interfaces
$networks = nic_targets_for($con, $connection);

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
            // vCPUs online now (hot-plug up to the topology maximum) and pinning to host CPUs
            $current_vcpus = (int)($_POST['current_vcpus'] ?? $vcpus);
            $cpuset = str_replace(' ', '', (string)($_POST['cpuset'] ?? ''));
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
            if ($current_vcpus < 1 || $current_vcpus > $vcpus) {
                throw new RuntimeException(t('Current vCPUs must be between 1 and the maximum (sockets x cores x threads).'));
            }
            if ($cpuset !== '' && !vm_cpuset_valid($cpuset, $max_vcpus)) {
                throw new RuntimeException(t('Host CPUs: a list such as 0-3 or 2,3 within 0-%d.', $max_vcpus - 1));
            }
            $old_vcpu = vm_vcpu_settings($xml);
            $old_vcpus = $old_cpu['sockets'] * $old_cpu['cores'] * $old_cpu['threads'];
            define_or_fail($con, vm_xml_set_vcpu_options(vm_xml_set_resources($xml, $memory, $vcpus, $current, $cpu), $current_vcpus, $cpuset));
            $details = sprintf('%d/%d MB, %d/%d vCPU (%dx%dx%d), %s, cpuset %s', $current, $memory, $current_vcpus, $vcpus,
                $cpu['sockets'], $cpu['cores'], $cpu['threads'], $cpu['mode'], $cpuset ?: '-');
            $message = t('Memory and vCPUs saved.');
            // the balloon target of a running machine changes at once (up to the current maximum)
            $only_balloon = $memory === $old_max && $cpu == $old_cpu && $vcpus === $old_vcpus;
            if ($active && $current !== vm_memory_mb($xml, 'currentMemory') && $current <= $old_max) {
                $message .= libvirt_domain_set_memory_flags($res, $current * 1024, AFFECT_LIVE)
                    ? ' '.t('The running machine now has %d MB.', $current)
                    : ' '.t('Live change failed (%s).', libvirt_get_last_error());
            }
            // vCPU hot-plug and pinning of the running machine (the maximum stays until a restart)
            $live_vcpus = $active && $vcpus === $old_vcpus;
            if ($live_vcpus && $current_vcpus !== $old_vcpu['current']) {
                [$status, $output] = domain_virsh($connection, ['setvcpus', $node, (string)$current_vcpus, '--live']);
                $message .= ' '.($status === 0 ? t('The running machine now has %d vCPUs.', $current_vcpus) : t('Live change failed (%s).', trim($output)));
            }
            if ($active && $cpuset !== $old_vcpu['cpuset']) {
                $pin = $cpuset !== '' ? $cpuset : '0-'.($max_vcpus - 1);
                $failed = '';
                for ($i = 0; $i < min($current_vcpus, $old_vcpu['current']); $i++) {
                    [$status, $output] = domain_virsh($connection, ['vcpupin', $node, (string)$i, $pin, '--live']);
                    $failed = $status === 0 ? $failed : trim($output);
                }
                $message .= ' '.($failed === '' ? t('vCPU pinning applied to the running machine.') : t('Live change failed (%s).', $failed));
            }
            $only_balloon = $only_balloon && $current_vcpus === $old_vcpu['current'] && $cpuset === $old_vcpu['cpuset'];
            if ($active && !$only_balloon && !($live_vcpus && $memory === $old_max && $cpu == $old_cpu)) {
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
            $source = (string)($_POST['source'] ?? 'new');
            $bus = (string)($_POST['bus'] ?? 'virtio');
            $cache = (string)($_POST['cache'] ?? '');
            if (!isset(DISK_BUSES[$bus])) {
                throw new RuntimeException(t('Unknown disk bus.'));
            }
            if (!isset(DISK_CACHE_MODES[$cache])) {
                throw new RuntimeException(t('Unknown cache mode.'));
            }
            $target = vm_next_target($xml, vm_disk_target_prefix($bus));
            $vol = null;
            if ($source === 'existing') {
                // an existing, unused volume (e.g. a disk image moved from another host)
                $path = (string)($_POST['volume'] ?? '');
                $free = storage_free_volumes($con);
                if (!isset($free[$path])) {
                    throw new RuntimeException(t('Select a volume that is not used by a machine.'));
                }
                $device = vm_disk_device_xml($path, $target, $bus, $free[$path]['format'], $cache);
                $details = $target.' '.$path;
                $message = t('Disk %s attached (%s).', $target, basename($path));
            }
            else {
                $size = (int)($_POST['size'] ?? 0);
                $pool = (string)($_POST['pool'] ?? '');
                if ($size < 1 || $size > 4096) {
                    throw new RuntimeException(t('Disk size must be between %d and %d GB.', 1, 4096));
                }
                if (!isset($pools[$pool])) {
                    throw new RuntimeException(t('Select an active storage pool.'));
                }
                $vol = storage_create_qcow2($con, $pool, $node.'-'.$target.'.qcow2', $size);
                $device = vm_disk_device_xml((string)libvirt_storagevolume_get_path($vol), $target, $bus, 'qcow2', $cache);
                $details = $target.', '.$size.' GB in '.$pool;
                $message = t('Disk %s (%d GB) added.', $target, $size);
            }
            // SCSI disks need a virtio-scsi controller
            if ($bus === 'scsi' && !vm_has_virtio_scsi($xml)) {
                $controller = "<controller type='scsi' model='virtio-scsi'/>";
                if (attach_device($con, $res, $xml, $active, $controller) === false) {
                    if ($vol) {
                        libvirt_storagevolume_delete($vol, 0);
                    }
                    throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                }
                $xml = domain_definition_xml($res);
            }
            $note = attach_device($con, $res, $xml, $active, $device);
            if ($note === false) {
                $error = libvirt_get_last_error();
                if ($vol) {
                    libvirt_storagevolume_delete($vol, 0);
                }
                throw new RuntimeException(t('Error: %s', $error));
            }
            $details .= ', '.$bus.($cache !== '' ? ', cache '.$cache : '').($note ? ', after restart' : '');
            $message .= $note;
        }
        elseif ($action === 'add_nic') {
            $network = nic_target_normalize((string)($_POST['network'] ?? ''));
            if (!nic_target_valid($network, $networks)) {
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
            $network = nic_target_normalize((string)($_POST['network'] ?? ''));
            $model = (string)($_POST['model'] ?? '');
            $nics = vm_nic_devices($xml);
            if (!isset($nics[$mac])) {
                throw new RuntimeException(t('Unknown network interface.'));
            }
            if (!nic_target_valid($network, $networks)) {
                throw new RuntimeException(t('Select a network.'));
            }
            if (!isset(NIC_MODELS[$model])) {
                throw new RuntimeException(t('Unknown network card model.'));
            }
            $new_nic = vm_nic_xml_change($nics[$mac]['xml'], $network, $model);
            $model_changed = $model !== $nics[$mac]['model'];
            if ($active && !$model_changed && libvirt_domain_update_device($res, $new_nic, AFFECT_LIVE_CONFIG)) {
                // switched in the running machine (the link stays, the new connection at once)
                $message = t('Network interface %s connected to %s.', $mac, nic_target_parse($network)[1]);
            }
            elseif ($active && !$model_changed) {
                // some changes (e.g. network -> macvtap) cannot be made live: the definition only
                define_or_fail($con, vm_xml_replace_nic($xml, $mac, $new_nic));
                $message = t('Network interface %s saved.', $mac).$after_restart;
            }
            else {
                define_or_fail($con, vm_xml_replace_nic($xml, $mac, $new_nic));
                $message = t('Network interface %s saved.', $mac).($model_changed ? $after_restart : '');
            }
            $details = $mac.': '.$network.', '.$model;
        }
        elseif ($action === 'hostdev_attach' || $action === 'hostdev_detach') {
            if (!can('admin')) {
                throw new RuntimeException(t('You do not have permission for this action.'));
            }
            $key = (string)($_POST['device'] ?? '');
            $attached = vm_hostdevs($xml);
            if ($action === 'hostdev_attach') {
                $available = hostdev_usb_list($con) + hostdev_pci_list($con);
                $device = isset($available[$key]) ? hostdev_xml($key) : null;
                if ($device === null) {
                    throw new RuntimeException(t('Unknown host device.'));
                }
                if (isset($attached[$key])) {
                    throw new RuntimeException(t('The device is already attached.'));
                }
                // USB goes live into a running machine; PCI only into the definition (next start)
                if (str_starts_with($key, 'usb:')) {
                    $note = attach_device($con, $res, $xml, $active, $device);
                    if ($note === false) {
                        throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                    }
                }
                else {
                    define_or_fail($con, vm_xml_add_device($xml, $device));
                    $note = $after_restart;
                }
                $message = t('Host device %s attached.', $available[$key]['label']).$note;
            }
            else {
                if (!isset($attached[$key])) {
                    throw new RuntimeException(t('Unknown host device.'));
                }
                if ($active) {
                    if (!libvirt_domain_detach_device($res, $attached[$key], AFFECT_LIVE_CONFIG)) {
                        throw new RuntimeException(t('Error: %s', libvirt_get_last_error()));
                    }
                }
                else {
                    define_or_fail($con, vm_xml_remove_hostdev($xml, $key));
                }
                $message = t('Host device %s detached.', $key);
            }
            $details = $key;
        }
        elseif ($action === 'disk_iotune') {
            $target = (string)($_POST['target'] ?? '');
            $mbs = (float)($_POST['mbs'] ?? 0);
            $iops = (int)($_POST['iops'] ?? 0);
            if (!isset(vm_disk_devices($xml)[$target]) || vm_disk_devices($xml)[$target]['device'] !== 'disk') {
                throw new RuntimeException(t('Unknown disk.'));
            }
            if ($mbs < 0 || $mbs > 100000 || $iops < 0 || $iops > 10000000) {
                throw new RuntimeException(t('Limits must be 0 (none) or positive numbers.'));
            }
            // --config always, --live as well for a running machine; 0 removes a limit
            $args = ['blkdeviotune', $node, $target, '--total-bytes-sec', (string)(int)round($mbs * 1048576), '--total-iops-sec', (string)$iops, '--config'];
            if ($active) {
                $args[] = '--live';
            }
            [$status, $output] = domain_virsh($connection, $args);
            if ($status !== 0) {
                throw new RuntimeException(t('Error: %s', trim($output)));
            }
            $details = $target.': '.$mbs.' MB/s, '.$iops.' IOPS';
            $message = t('I/O limits of %s saved.', $target);
        }
        elseif ($action === 'nic_bandwidth') {
            $mac = strtolower((string)($_POST['mac'] ?? ''));
            $in = (float)($_POST['in'] ?? 0);
            $out = (float)($_POST['out'] ?? 0);
            if (!isset(vm_nic_devices($xml)[$mac])) {
                throw new RuntimeException(t('Unknown network interface.'));
            }
            if ($in < 0 || $in > 100000 || $out < 0 || $out > 100000) {
                throw new RuntimeException(t('Limits must be 0 (none) or positive numbers.'));
            }
            // inbound = traffic to the machine (download), outbound = from it; KiB/s, 0 removes
            $args = ['domiftune', $node, $mac, '--inbound', (string)vm_mbit_to_kib($in), '--outbound', (string)vm_mbit_to_kib($out), '--config'];
            if ($active) {
                $args[] = '--live';
            }
            [$status, $output] = domain_virsh($connection, $args);
            if ($status !== 0) {
                throw new RuntimeException(t('Error: %s', trim($output)));
            }
            $details = $mac.': in '.$in.' Mbit/s, out '.$out.' Mbit/s';
            $message = t('Bandwidth limits of %s saved.', $mac);
        }
        elseif ($action === 'describe') {
            $title = trim(preg_replace('/\s+/', ' ', (string)($_POST['title'] ?? '')));
            $description = trim(str_replace("\r\n", "\n", (string)($_POST['description'] ?? '')));
            if (mb_strlen($title) > 120 || mb_strlen($description) > 4000) {
                throw new RuntimeException(t('Title: up to 120 characters, description: up to 4000.'));
            }
            // virsh desc changes the definition and, for a running machine, the live state
            foreach (['--title' => $title, '' => $description] as $flag => $text) {
                $args = array_values(array_filter(['desc', $node, '--config', $active ? '--live' : '', $flag, '--new-desc', $text], fn($a) => $a !== ''));
                // an empty text must still be passed to clear the field
                if ($text === '') {
                    $args[] = '';
                }
                [$status, $output] = domain_virsh($connection, $args);
                if ($status !== 0) {
                    throw new RuntimeException(t('Error: %s', trim($output)));
                }
            }
            $details = $title;
            $message = t('Title and description saved.');
        }
        elseif ($action === 'firmware') {
            $firmware = (string)($_POST['firmware'] ?? 'bios');
            if ($active) {
                throw new RuntimeException(t('Shut the machine down first.'));
            }
            if (!isset(DOMAIN_FIRMWARES[$firmware])) {
                throw new RuntimeException(t('Select the firmware.'));
            }
            $current_fw = vm_firmware_settings($xml);
            if ($firmware === 'efi-secure' && !$current_fw['q35']) {
                throw new RuntimeException(t('Secure Boot needs the q35 machine type.'));
            }
            define_or_fail($con, vm_xml_set_firmware($xml, $firmware, !empty($_POST['tpm'])));
            $details = $current_fw['firmware'].' -> '.$firmware.', tpm '.(empty($_POST['tpm']) ? 'off' : 'on');
            $message = t('Firmware and TPM saved.');
        }
        elseif ($action === 'hardware') {
            $video = (string)($_POST['video'] ?? 'virtio');
            $sound = (string)($_POST['sound'] ?? '');
            $watchdog = (string)($_POST['watchdog'] ?? '');
            if (!isset(VM_VIDEO_MODELS[$video]) || !isset(VM_SOUND_MODELS[$sound]) || !isset(VM_WATCHDOG_ACTIONS[$watchdog])) {
                throw new RuntimeException(t('Unknown device model.'));
            }
            define_or_fail($con, vm_xml_set_hardware($xml, $video, $sound, $watchdog, !empty($_POST['rng'])));
            $details = 'video '.$video.', sound '.($sound ?: '-').', watchdog '.($watchdog ?: '-').', rng '.(empty($_POST['rng']) ? 'off' : 'on');
            $message = t('Hardware saved.').$after_restart;
        }
        elseif ($action === 'vnc_password') {
            $password = (string)($_POST['vnc_password'] ?? '');
            // VNC passwords are limited to 8 characters by the protocol
            if ($password !== '' && !preg_match('/^[\x21-\x7e]{1,8}$/', $password)) {
                throw new RuntimeException(t('VNC password: 1-8 printable characters without spaces.'));
            }
            define_or_fail($con, vm_xml_set_vnc_password($xml, $password));
            $details = $password === '' ? 'removed' : 'set';
            $message = ($password === '' ? t('VNC password removed.') : t('VNC password set; the browser console uses it automatically.')).$after_restart;
        }
        elseif ($action === 'fs_add' || $action === 'fs_remove') {
            if (!can('admin')) {
                throw new RuntimeException(t('You do not have permission for this action.'));
            }
            $tag = (string)($_POST['tag'] ?? '');
            if (!preg_match(VM_FS_TAG_PATTERN, $tag)) {
                throw new RuntimeException(t('Mount tag: letters, digits, _ - (up to 36 characters).'));
            }
            if ($action === 'fs_add') {
                $source = rtrim(trim((string)($_POST['source'] ?? '')), '/');
                if ($source === '' || !preg_match(STORAGE_PATH_PATTERN, $source) || str_contains($source, '..')) {
                    throw new RuntimeException(t('Host directory: an absolute path such as /srv/share.'));
                }
                if (isset(vm_hardware($xml)['filesystems'][$tag])) {
                    throw new RuntimeException(t('The mount tag %s is already used.', $tag));
                }
                define_or_fail($con, vm_xml_add_filesystem($xml, $source, $tag));
                $details = $source.' as '.$tag;
                $message = t('Directory %s shared as %s (in the guest: mount -t virtiofs %s /mnt).', $source, $tag, $tag).$after_restart;
            }
            else {
                define_or_fail($con, vm_xml_remove_filesystem($xml, $tag));
                $details = $tag;
                $message = t('Shared folder %s removed.', $tag).$after_restart;
            }
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
            $modes = vm_clone_disk_modes($xml, (array)($_POST['disk_mode'] ?? []));
            $id = job_create('clone', ['source' => $node, 'name' => $name, 'disks' => $modes]);
            action_log('clone_queued', $node, true, $name.' (job #'.$id.') '.json_encode($modes));
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
$smarty->assign('vcpu', vm_vcpu_settings($xml));
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
    $iotune = vm_disk_iotune($xml, $target);
    $device_disks[] = $d + ['target' => $target, 'file' => basename($d['path']),
        'limit_mbs' => round($iotune['bytes'] / 1048576, 1), 'limit_iops' => $iotune['iops'], 'capacity' => $capacity !== null ? format_bytes($capacity) : '-',
        'capacity_gb' => $capacity !== null ? (int)ceil($capacity / 1073741824) : 0,
        'shared' => in_array($d['path'], $shared, true)];
}
$smarty->assign('device_disks', $device_disks);
$device_nics = vm_nic_devices($xml);
foreach ($device_nics as $mac => $nic) {
    $bandwidth = vm_nic_bandwidth($xml, $mac);
    $device_nics[$mac] += ['limit_in' => vm_kib_to_mbit($bandwidth['in']), 'limit_out' => vm_kib_to_mbit($bandwidth['out'])];
}
$smarty->assign('device_nics', $device_nics);
$smarty->assign('nic_models', NIC_MODELS);
$smarty->assign('disk_buses', DISK_BUSES);
$smarty->assign('disk_cache_modes', DISK_CACHE_MODES);
$smarty->assign('free_volumes', storage_free_volumes($con));
$smarty->assign('serial_port', vm_serial_port($xml));
$smarty->assign('hardware', vm_hardware($xml));
$smarty->assign('firmware', vm_firmware_settings($xml));
$xml_doc = simplexml_load_string($xml);
$smarty->assign('vm_title', $xml_doc ? trim((string)$xml_doc->title) : '');
$smarty->assign('vm_description', $xml_doc ? (string)$xml_doc->description : '');
$smarty->assign('firmwares', DOMAIN_FIRMWARES);
$smarty->assign('video_models', VM_VIDEO_MODELS);
$smarty->assign('sound_models', VM_SOUND_MODELS);
$smarty->assign('watchdog_actions', VM_WATCHDOG_ACTIONS);
// host devices: only administrators see and change them
if (can('admin')) {
    $host_devices = hostdev_usb_list($con) + hostdev_pci_list($con);
    $smarty->assign('host_devices', $host_devices);
    $smarty->assign('vm_hostdevs', array_map(fn($key) => ['key' => $key, 'label' => $host_devices[$key]['label'] ?? t('(not present on the host)')],
        array_keys(vm_hostdevs($xml))));
}
$smarty->display('edit.tpl');
