<?php

// libvirt domain states (virDomainState) => [label, bootstrap color]
const DOMAIN_STATES = [
    0 => ['no state', 'secondary'],
    1 => ['running', 'success'],
    2 => ['blocked', 'warning'],
    3 => ['paused', 'warning'],
    4 => ['shutting down', 'info'],
    5 => ['shut off', 'danger'],
    6 => ['crashed', 'dark'],
    7 => ['suspended', 'warning'],
];

// power action => [libvirt function, message on success]
// operating system profiles of the new machine wizard: recommended resources and,
// for Windows, Hyper-V enlightenments, local time clock and installer-friendly devices
const DOMAIN_OS_PROFILES = [
    'linux'  => ['label' => 'Linux / other', 'memory' => 2048, 'disk' => 20, 'vcpus' => 2, 'firmware' => 'bios', 'tpm' => 0, 'windows' => false],
    'win11'  => ['label' => 'Windows 11', 'memory' => 4096, 'disk' => 64, 'vcpus' => 2, 'firmware' => 'efi-secure', 'tpm' => 1, 'windows' => true],
    'win10'  => ['label' => 'Windows 10', 'memory' => 4096, 'disk' => 64, 'vcpus' => 2, 'firmware' => 'efi', 'tpm' => 0, 'windows' => true],
    'winsrv' => ['label' => 'Windows Server 2022/2025', 'memory' => 4096, 'disk' => 64, 'vcpus' => 2, 'firmware' => 'efi-secure', 'tpm' => 1, 'windows' => true],
];
const VIRTIO_WIN_ISO_URL = 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/stable-virtio/virtio-win.iso';

// Windows guests: Hyper-V enlightenments (much faster, fewer timer problems)
function domain_hyperv_xml() {
    return "<hyperv mode='custom'><relaxed state='on'/><vapic state='on'/><spinlocks state='on' retries='8191'/>"
        ."<vpindex state='on'/><runtime state='on'/><synic state='on'/><stimer state='on'/><reset state='on'/>"
        ."<frequencies state='on'/></hyperv>";
}

// firmware choices of the new machine wizard
const DOMAIN_FIRMWARES = ['bios' => 'BIOS', 'efi' => 'UEFI', 'efi-secure' => 'UEFI + Secure Boot'];

// key combinations sent to the guest (Linux input key codes): label, codes
const DOMAIN_KEY_COMBOS = [
    'ctrl-alt-del'       => ['Ctrl+Alt+Del', [29, 56, 111]],
    'ctrl-alt-backspace' => ['Ctrl+Alt+Backspace', [29, 56, 14]],
    'ctrl-alt-f1'        => ['Ctrl+Alt+F1', [29, 56, 59]],
    'ctrl-alt-f2'        => ['Ctrl+Alt+F2', [29, 56, 60]],
    'ctrl-alt-f3'        => ['Ctrl+Alt+F3', [29, 56, 61]],
    'ctrl-alt-f7'        => ['Ctrl+Alt+F7', [29, 56, 65]],
];

const DOMAIN_POWER_ACTIONS = [
    'start'   => ['libvirt_domain_create',   'Starting machine, it may take some time'],
    'stop'    => ['libvirt_domain_shutdown', 'Shutting down machine, it may take some time'],
    'destroy' => ['libvirt_domain_destroy',  'Machine forcibly stopped'],
    'reboot'  => ['libvirt_domain_reboot',   'Rebooting machine, it may take some time'],
    'suspend' => ['libvirt_domain_suspend',  'Suspending machine'],
    'resume'  => ['libvirt_domain_resume',   'Resuming machine'],
    'reset'   => ['domain_reset',            'Machine reset'],
    // managed save: memory written to disk, the machine stops; the next start restores it
    'save'    => ['libvirt_domain_managedsave', 'Machine state saved; the next start restores it'],
];

// hard reset, like the reset button (no clean shutdown)
function domain_reset($res) {
    return libvirt_domain_reset($res, 0);
}

// virsh command for what libvirt-php lacks (rename, managed save state, migration);
// [exit code, output]; $tick($output) is called about once a second
function domain_virsh($uri, array $args, ?callable $tick = null) {
    return cloud_run(array_merge(['virsh', '-q', '-c', $uri], $args), $tick);
}

// true when the stopped machine has a saved state that the next start restores
function domain_has_managed_save($uri, $name) {
    [$status, $output] = domain_virsh($uri, ['dominfo', $name]);
    return $status === 0 && (bool)preg_match('/^Managed save:\s+yes/mi', $output);
}

// VM name allowed by the create wizard
const DOMAIN_NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/';

function domain_state($state) {
    [$label, $color] = DOMAIN_STATES[$state] ?? ['unknown', 'secondary'];
    return ['id' => $state, 'label' => $label, 'color' => $color];
}

// all domains with their state, sorted by name
function domain_list($con) {
    $list = [];
    foreach (libvirt_list_domains($con) ?: [] as $name) {
        $res = libvirt_domain_lookup_by_name($con, $name);
        $info = $res ? libvirt_domain_get_info($res) : ['state' => -1];
        $list[] = ['name' => $name] + domain_state($info['state']);
    }
    usort($list, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    return $list;
}

// machines for the hypervisor page: state, vCPUs, memory, guest IPs and disk use
function domain_overview($con) {
    $stats = @libvirt_connect_get_all_domain_stats($con, 0, 0) ?: [];
    $rows = [];
    foreach (domain_list($con) as $d) {
        $res = libvirt_domain_lookup_by_name($con, $d['name']);
        if (!$res) {
            continue;
        }
        $info = libvirt_domain_get_info($res);
        $xml = domain_xml($res);
        $active = in_array((int)$d['id'], [1, 2, 3], true);
        $addresses = [];
        foreach ($xml ? domain_interfaces($res, $xml, $active) : [] as $iface) {
            $addresses = array_merge($addresses, $iface['addresses']);
        }
        $capacity = $allocation = 0;
        foreach ($xml ? $xml->devices->disk : [] as $disk) {
            if ((string)$disk['device'] === 'disk') {
                $block = @libvirt_domain_get_block_info($res, (string)$disk->target['dev']);
                if (is_array($block)) {
                    $capacity += (int)$block['capacity'];
                    $allocation += (int)$block['allocation'];
                }
            }
        }
        $counters = isset($stats[$d['name']]) ? stats_counters($stats[$d['name']]) : null;
        [$mem_used, $mem_total, $mem_source] = $active && $counters ? stats_memory_used($counters) : [0, (int)$info['memory'], ''];
        $rows[] = $d + [
            'active'     => $active,
            'vcpus'      => (int)$info['nrVirtCpu'],
            'mem_used'   => $mem_used * 1024,
            'mem_total'  => $mem_total * 1024,
            'mem_source' => $mem_source,
            'addresses'  => $addresses,
            'disk_alloc' => $allocation,
            'disk_cap'   => $capacity,
        ];
    }
    return $rows;
}

// persistent definition for changing and redefining a machine: with the SECURE flag,
// otherwise libvirt leaves out secrets such as the VNC password and a redefinition
// built from it would silently drop them
function domain_definition_xml($res) {
    return (string)libvirt_domain_get_xml_desc($res, null, VIR_DOMAIN_XML_INACTIVE | VIR_DOMAIN_XML_SECURE);
}

function domain_xml($res, $inactive = false) {
    $xml = libvirt_domain_get_xml_desc($res, null, $inactive ? VIR_DOMAIN_XML_INACTIVE : 0);
    return simplexml_load_string((string)$xml) ?: null;
}

function domain_disks($res, SimpleXMLElement $xml) {
    $disks = [];
    foreach ($xml->devices->disk as $disk) {
        $dev = (string)$disk->target['dev'];
        $source = (string)($disk->source['file'] ?? $disk->source['dev'] ?? $disk->source['volume'] ?? '');
        $size = '';
        if ($source !== '' && (string)$disk['device'] === 'disk') {
            $block = @libvirt_domain_get_block_info($res, $dev);
            if (is_array($block)) {
                $size = t('%s (used %s)', format_bytes($block['capacity']), format_bytes($block['allocation']));
            }
        }
        $disks[] = [
            'device' => (string)$disk['device'],
            'target' => $dev,
            'bus'    => (string)$disk->target['bus'],
            'source' => $source,
            'size'   => $size,
        ];
    }
    return $disks;
}

function domain_interfaces($res, SimpleXMLElement $xml, $active) {
    // guest IPs by MAC: DHCP lease first, then guest agent
    $ips = [];
    if ($active) {
        foreach ([0, 1] as $source) {
            foreach (@libvirt_domain_interface_addresses($res, $source) ?: [] as $iface) {
                $mac = strtolower((string)($iface['hwaddr'] ?? ''));
                foreach ($iface['addrs'] ?? [] as $addr) {
                    if (isset($addr['addr']) && !str_starts_with($addr['addr'], '127.') && $addr['addr'] !== '::1') {
                        $ips[$mac][] = $addr['addr'].'/'.($addr['prefix'] ?? '');
                    }
                }
            }
            if ($ips) {
                break;
            }
        }
    }

    $interfaces = [];
    foreach ($xml->devices->interface as $iface) {
        $mac = strtolower((string)$iface->mac['address']);
        $interfaces[] = [
            'type'   => (string)$iface['type'],
            'source' => (string)($iface->source['network'] ?? $iface->source['bridge'] ?? $iface->source['dev'] ?? ''),
            'mac'    => $mac,
            'model'  => (string)$iface->model['type'],
            'target' => (string)$iface->target['dev'],
            'ips'    => array_values(array_unique($ips[$mac] ?? [])),
        ];
        // address and prefix apart, so the address can be copied alone
        $interfaces[array_key_last($interfaces)]['addresses'] = array_map(function ($ip) {
            [$addr, $prefix] = array_pad(explode('/', $ip, 2), 2, '');
            return ['addr' => $addr, 'prefix' => $prefix];
        }, $interfaces[array_key_last($interfaces)]['ips']);
    }
    return $interfaces;
}

function domain_graphics(SimpleXMLElement $xml) {
    $graphics = $xml->devices->graphics[0] ?? null;
    if (!$graphics) {
        return null;
    }
    return [
        'type'   => (string)$graphics['type'],
        'port'   => (int)$graphics['port'],
        'listen' => (string)($graphics['listen'] ?? ''),
    ];
}

// libvirt-php 0.5.x: a snapshot resource does not keep a reference to its
// domain, so freeing the domain first makes the snapshot destructor crash PHP.
// Every snapshot resource must be released (unset) while the domain is alive.

// list of snapshots with details, newest first
function domain_snapshots($res) {
    $snapshots = [];
    foreach (libvirt_list_domain_snapshots($res) ?: [] as $name) {
        $snap = libvirt_domain_snapshot_lookup_by_name($res, $name);
        $xml = $snap ? simplexml_load_string((string)libvirt_domain_snapshot_get_xml($snap)) : null;
        unset($snap);
        $snapshots[] = [
            'name'        => $name,
            'description' => $xml ? (string)$xml->description : '',
            'state'       => $xml ? (string)$xml->state : '',
            'created'     => $xml ? (int)$xml->creationTime : 0,
        ];
    }
    usort($snapshots, fn($a, $b) => $b['created'] <=> $a['created']);
    return $snapshots;
}

function domain_snapshot_xml($name, $description) {
    return '<domainsnapshot><name>'.xml_escape($name).'</name>'
        .'<description>'.xml_escape($description).'</description></domainsnapshot>';
}

// XML <graphics> element for VNC reachable only from localhost
function vnc_graphics_xml() {
    return "<graphics type='vnc' port='-1' autoport='yes' listen='127.0.0.1'><listen type='address' address='127.0.0.1'/></graphics>";
}

// replaces SPICE graphics with VNC in the persistent definition;
// spice-only devices (agent channel, USB redirection, spice audio) are removed
// because libvirt refuses them without SPICE graphics
function domain_xml_spice_to_vnc($xml) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $previous = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return null;
    }
    $xpath = new DOMXPath($doc);
    $spice = $xpath->query("/domain/devices/graphics[@type='spice']");
    if (!$spice || $spice->length === 0) {
        return null;
    }
    foreach ($spice as $i => $node) {
        if ($i === 0) {
            $vnc = $doc->createDocumentFragment();
            $vnc->appendXML(vnc_graphics_xml());
            $node->parentNode->replaceChild($vnc, $node);
        }
        else {
            $node->parentNode->removeChild($node);
        }
    }
    foreach ($xpath->query("/domain/devices/channel[@type='spicevmc'] | /domain/devices/redirdev[@type='spicevmc']") ?: [] as $node) {
        $node->parentNode->removeChild($node);
    }
    foreach ($xpath->query("/domain/devices/audio[@type='spice']") ?: [] as $node) {
        if ($node instanceof DOMElement) {
            $node->setAttribute('type', 'none');
        }
    }
    return $doc->saveXML($doc->documentElement);
}

// XML definition for a new VM created by the wizard; $iso_bus 'scsi' puts the
// CD-ROM on a virtio-scsi controller: cloud kernels (e.g. Debian 12 "cloud-amd64")
// have no AHCI driver and would not see a SATA CD-ROM, while installer ISOs
// (Windows without drivers) need SATA
// $firmware: 'bios' (SeaBIOS), 'efi' or 'efi-secure' (UEFI with Secure Boot and the
// Microsoft keys enrolled, e.g. for Windows 11) - the firmware files are chosen by libvirt;
// $tpm adds an emulated TPM 2.0 (swtpm)
// $disk_format: qcow2 or raw (an imported volume); $opts: windows (bool), disk_bus,
// nic_model, drivers_iso (second CD-ROM, e.g. virtio-win drivers)
function domain_new_xml($name, $memory_mb, $vcpus, $disk_path, $iso_path, $network, array $boot = ['cdrom', 'hd'], $iso_bus = 'sata', $serial_port = 0, $firmware = 'bios', $tpm = false, $disk_format = 'qcow2', array $opts = []) {
    $windows = !empty($opts['windows']);
    $drivers = (string)($opts['drivers_iso'] ?? '');
    $iso = '';
    if ($iso_path !== '') {
        $bus = $iso_bus === 'scsi' ? 'scsi' : 'sata';
        $iso = "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/>"
            ."<source file='".xml_escape($iso_path)."'/><target dev='sda' bus='".$bus."'/><readonly/></disk>"
            .($bus === 'scsi' ? "<controller type='scsi' model='virtio-scsi'/>" : '');
    }
    return "<domain type='kvm'>"
        .'<name>'.xml_escape($name).'</name>'
        ."<memory unit='MiB'>".(int)$memory_mb.'</memory>'
        .'<vcpu>'.(int)$vcpus.'</vcpu>'
        .($firmware === 'bios' ? '<os>' : "<os firmware='efi'>")
        ."<type arch='x86_64' machine='q35'>hvm</type>"
        .($firmware === 'efi-secure'
            ? "<firmware><feature enabled='yes' name='secure-boot'/><feature enabled='yes' name='enrolled-keys'/></firmware>"
            : ($firmware === 'efi' ? "<firmware><feature enabled='no' name='secure-boot'/></firmware>" : ''))
        .implode('', array_map(fn($dev) => "<boot dev='".xml_escape($dev)."'/>", $boot)).'</os>'
        // Secure Boot needs SMM so the firmware variables cannot be changed by the guest
        .'<features><acpi/><apic/>'.($windows ? domain_hyperv_xml() : '').($firmware === 'efi-secure' ? "<smm state='on'/>" : '').'</features>'
        ."<cpu mode='host-passthrough'/>"
        // Windows keeps the hardware clock in local time
        .($windows
            ? "<clock offset='localtime'><timer name='rtc' tickpolicy='catchup'/><timer name='pit' tickpolicy='delay'/>"
                ."<timer name='hpet' present='no'/><timer name='hypervclock' present='yes'/></clock>"
            : "<clock offset='utc'/>")
        .'<on_poweroff>destroy</on_poweroff><on_reboot>restart</on_reboot><on_crash>destroy</on_crash>'
        .'<devices>'
        .vm_disk_device_xml($disk_path, ($opts['disk_bus'] ?? 'virtio') === 'virtio' ? 'vda' : 'sdc', $opts['disk_bus'] ?? 'virtio', $disk_format)
        .$iso
        .($drivers !== '' ? "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/>"
            ."<source file='".xml_escape($drivers)."'/><target dev='sdb' bus='sata'/><readonly/></disk>" : '')
        .vm_nic_device_xml($network, $opts['nic_model'] ?? 'virtio')
        .vnc_graphics_xml()
        ."<video><model type='virtio'/></video>"
        ."<input type='tablet' bus='usb'/>"
        .vm_serial_xml($serial_port)
        // guest agent channel: IP addresses and clean shutdown when qemu-guest-agent runs in the guest
        ."<channel type='unix'><target type='virtio' name='org.qemu.guest_agent.0'/></channel>"
        ."<memballoon model='virtio'/>"
        .($tpm ? "<tpm model='tpm-crb'><backend type='emulator' version='2.0'/></tpm>" : '')
        // spare PCIe root ports: q35 needs a free port for every hot-plugged disk or NIC
        ."<controller type='pci' index='0' model='pcie-root'/>"
        .str_repeat("<controller type='pci' model='pcie-root-port'/>", 8)
        .'</devices></domain>';
}

function volume_new_xml($name, $capacity_gb) {
    return '<volume><name>'.xml_escape($name).'</name>'
        ."<capacity unit='G'>".(int)$capacity_gb.'</capacity>'
        ."<target><format type='qcow2'/></target></volume>";
}

// after a rename: starred machines and snapshot schedules follow the new name
function domain_rename_references($conn, $old, $new) {
    db_query('UPDATE favorites SET name = ? WHERE conn = ? AND name = ?', [$new, $conn, $old]);
    db_query('UPDATE schedules SET domain = ? WHERE conn = ? AND domain = ?', [$new, $conn, $old]);
}

// Linux input key codes of a text typed into a machine (US keyboard layout):
// one entry per character, [code] or [shift, code]; null when a character cannot be typed
function domain_text_keycodes($text) {
    static $map = null;
    if ($map === null) {
        $map = [];
        $rows = [
            // unshifted => shifted, key code
            ['1', '!', 2], ['2', '@', 3], ['3', '#', 4], ['4', '$', 5], ['5', '%', 6], ['6', '^', 7], ['7', '&', 8],
            ['8', '*', 9], ['9', '(', 10], ['0', ')', 11], ['-', '_', 12], ['=', '+', 13], ['[', '{', 26], [']', '}', 27],
            [';', ':', 39], ["'", '"', 40], ['`', '~', 41], ['\\', '|', 43], [',', '<', 51], ['.', '>', 52], ['/', '?', 53],
        ];
        foreach ($rows as [$plain, $shifted, $code]) {
            $map[$plain] = [$code];
            $map[$shifted] = [42, $code];
        }
        foreach (['q' => 16, 'w' => 17, 'e' => 18, 'r' => 19, 't' => 20, 'y' => 21, 'u' => 22, 'i' => 23, 'o' => 24, 'p' => 25,
                  'a' => 30, 's' => 31, 'd' => 32, 'f' => 33, 'g' => 34, 'h' => 35, 'j' => 36, 'k' => 37, 'l' => 38,
                  'z' => 44, 'x' => 45, 'c' => 46, 'v' => 47, 'b' => 48, 'n' => 49, 'm' => 50] as $letter => $code) {
            $map[$letter] = [$code];
            $map[strtoupper($letter)] = [42, $code];
        }
        $map[' '] = [57];
        $map["\n"] = [28];
        $map["\t"] = [15];
    }
    $codes = [];
    foreach (preg_split('//u', str_replace("\r\n", "\n", (string)$text), -1, PREG_SPLIT_NO_EMPTY) as $char) {
        if (!isset($map[$char])) {
            return null;
        }
        $codes[] = $map[$char];
    }
    return $codes;
}

// after a migration: starred machines and snapshot schedules move to the destination connection
function domain_move_references($from_conn, $to_conn, $name) {
    db_query('UPDATE OR IGNORE favorites SET conn = ? WHERE conn = ? AND name = ?', [$to_conn, $from_conn, $name]);
    db_query('UPDATE schedules SET conn = ? WHERE conn = ? AND domain = ?', [$to_conn, $from_conn, $name]);
}
