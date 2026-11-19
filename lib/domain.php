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
];

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
function domain_new_xml($name, $memory_mb, $vcpus, $disk_path, $iso_path, $network, array $boot = ['cdrom', 'hd'], $iso_bus = 'sata') {
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
        ."<os><type arch='x86_64' machine='q35'>hvm</type>"
        .implode('', array_map(fn($dev) => "<boot dev='".xml_escape($dev)."'/>", $boot)).'</os>'
        .'<features><acpi/><apic/></features>'
        ."<cpu mode='host-passthrough'/>"
        ."<clock offset='utc'/>"
        .'<on_poweroff>destroy</on_poweroff><on_reboot>restart</on_reboot><on_crash>destroy</on_crash>'
        .'<devices>'
        ."<disk type='file' device='disk'><driver name='qemu' type='qcow2' discard='unmap'/>"
        ."<source file='".xml_escape($disk_path)."'/><target dev='vda' bus='virtio'/></disk>"
        .$iso
        ."<interface type='network'><source network='".xml_escape($network)."'/><model type='virtio'/></interface>"
        .vnc_graphics_xml()
        ."<video><model type='virtio'/></video>"
        ."<input type='tablet' bus='usb'/>"
        ."<console type='pty'/>"
        // guest agent channel: IP addresses and clean shutdown when qemu-guest-agent runs in the guest
        ."<channel type='unix'><target type='virtio' name='org.qemu.guest_agent.0'/></channel>"
        ."<memballoon model='virtio'/>"
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
