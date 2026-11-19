<?php

// transformations of a domain XML definition used by the edit and clone
// pages; pure functions (no libvirt calls) returning the new XML

function vm_dom($xml) {
    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $previous = libxml_use_internal_errors(true);
    $loaded = $doc->loadXML((string)$xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        throw new InvalidArgumentException('Invalid domain XML');
    }
    return $doc;
}

function vm_xml(DOMDocument $doc) {
    return (string)$doc->saveXML($doc->documentElement);
}

// first element matching the XPath query, null when none
function vm_first(DOMDocument $doc, $query) {
    $node = (new DOMXPath($doc))->query($query)->item(0);
    return $node instanceof DOMElement ? $node : null;
}

function vm_element(DOMDocument $doc, $xml) {
    $fragment = $doc->createDocumentFragment();
    $fragment->appendXML($xml);
    return $fragment;
}

function vm_set_text(DOMDocument $doc, $name, $value, array $attributes = []) {
    $node = vm_first($doc, '/domain/'.$name);
    if (!$node) {
        $node = $doc->createElement($name);
        $doc->documentElement->appendChild($node);
    }
    $node->textContent = (string)$value;
    foreach ($attributes as $attr => $attr_value) {
        $node->setAttribute($attr, $attr_value);
    }
}

// memory and vCPU count of the persistent definition
function vm_xml_set_resources($xml, $memory_mb, $vcpus) {
    $doc = vm_dom($xml);
    vm_set_text($doc, 'memory', (int)$memory_mb, ['unit' => 'MiB']);
    vm_set_text($doc, 'currentMemory', (int)$memory_mb, ['unit' => 'MiB']);
    vm_set_text($doc, 'vcpu', (int)$vcpus);
    return vm_xml($doc);
}

// target names (vda, sdb, ...) already used by disks
function vm_used_targets($xml) {
    $used = [];
    foreach ((new DOMXPath(vm_dom($xml)))->query('/domain/devices/disk/target/@dev') ?: [] as $attr) {
        $used[] = $attr->nodeValue;
    }
    return $used;
}

// first free target with the prefix: vda, vdb, ... vdz
function vm_next_target($xml, $prefix) {
    $used = vm_used_targets($xml);
    foreach (range('a', 'z') as $letter) {
        if (!in_array($prefix.$letter, $used, true)) {
            return $prefix.$letter;
        }
    }
    throw new RuntimeException('No free disk target');
}

function vm_disk_device_xml($path, $target) {
    return "<disk type='file' device='disk'><driver name='qemu' type='qcow2' discard='unmap'/>"
        ."<source file='".xml_escape($path)."'/><target dev='".xml_escape($target)."' bus='virtio'/></disk>";
}

function vm_cdrom_device_xml($iso_path, $target) {
    $source = $iso_path !== '' ? "<source file='".xml_escape($iso_path)."'/>" : '';
    return "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/>".$source
        ."<target dev='".xml_escape($target)."' bus='sata'/><readonly/></disk>";
}

function vm_nic_device_xml($network, $model = 'virtio') {
    return "<interface type='network'><source network='".xml_escape($network)."'/>"
        ."<model type='".xml_escape($model)."'/></interface>";
}

function vm_xml_add_device($xml, $device_xml) {
    $doc = vm_dom($xml);
    $devices = vm_first($doc, '/domain/devices');
    if (!$devices) {
        $devices = $doc->createElement('devices');
        $doc->documentElement->appendChild($devices);
    }
    $devices->appendChild(vm_element($doc, $device_xml));
    return vm_xml($doc);
}

// inserts or ejects the CD/DVD medium; adds a SATA CD drive when there is none.
// Returns [new XML, cdrom device XML usable for a live update or null when the drive is new]
function vm_xml_set_cdrom($xml, $iso_path) {
    $doc = vm_dom($xml);
    $cdrom = vm_first($doc, "/domain/devices/disk[@device='cdrom']");
    if (!$cdrom) {
        $target = vm_next_target($xml, 'sd');
        return [vm_xml_add_device($xml, vm_cdrom_device_xml($iso_path, $target)), null];
    }
    foreach (iterator_to_array($cdrom->getElementsByTagName('source')) as $source) {
        $cdrom->removeChild($source);
    }
    if ($iso_path !== '') {
        $source = $doc->createElement('source');
        $source->setAttribute('file', $iso_path);
        $driver = $cdrom->getElementsByTagName('driver')->item(0);
        $cdrom->insertBefore($source, $driver ? $driver->nextSibling : $cdrom->firstChild);
    }
    $device = (string)$doc->saveXML($cdrom);
    return [vm_xml($doc), $device];
}

// boot order from a list of hd, cdrom, network; device-level <boot order>
// entries are removed because libvirt does not allow both forms
function vm_xml_set_boot_order($xml, array $order) {
    $doc = vm_dom($xml);
    $os = vm_first($doc, '/domain/os');
    if (!$os) {
        throw new InvalidArgumentException('Domain has no <os> element');
    }
    foreach (iterator_to_array((new DOMXPath($doc))->query('/domain/os/boot | /domain/devices/*/boot')) as $boot) {
        $boot->parentNode->removeChild($boot);
    }
    $after = vm_first($doc, '/domain/os/type');
    foreach (array_unique($order) as $dev) {
        if (!in_array($dev, ['hd', 'cdrom', 'network'], true)) {
            throw new InvalidArgumentException('Unknown boot device '.$dev);
        }
        $boot = $doc->createElement('boot');
        $boot->setAttribute('dev', $dev);
        $os->insertBefore($boot, $after ? $after->nextSibling : null);
        $after = $boot;
    }
    return vm_xml($doc);
}

function vm_boot_order($xml) {
    $order = [];
    foreach ((new DOMXPath(vm_dom($xml)))->query('/domain/os/boot/@dev') ?: [] as $attr) {
        $order[] = $attr->nodeValue;
    }
    return $order;
}

// file-backed disks (not CD-ROMs): [target => path]
function vm_disk_paths($xml) {
    $paths = [];
    $xpath = new DOMXPath(vm_dom($xml));
    foreach ($xpath->query("/domain/devices/disk[@device='disk']") ?: [] as $disk) {
        $source = $xpath->query('source/@file', $disk)->item(0);
        $target = $xpath->query('target/@dev', $disk)->item(0);
        if ($source && $target) {
            $paths[$target->nodeValue] = $source->nodeValue;
        }
    }
    return $paths;
}

// cloud-init seed ISOs created for this machine (<name>-seed.iso): [target => path];
// unlike other CD/DVD images they may be deleted together with the machine
function vm_seed_paths($xml) {
    $paths = [];
    $xpath = new DOMXPath(vm_dom($xml));
    foreach ($xpath->query("/domain/devices/disk[@device='cdrom']") ?: [] as $disk) {
        $source = $xpath->query('source/@file', $disk)->item(0);
        $target = $xpath->query('target/@dev', $disk)->item(0);
        if ($source && $target && str_ends_with($source->nodeValue, '-seed.iso')) {
            $paths[$target->nodeValue] = $source->nodeValue;
        }
    }
    return $paths;
}

// all file sources (disks and CD-ROMs), used to detect disks shared between machines
function vm_all_sources($xml) {
    $paths = [];
    foreach ((new DOMXPath(vm_dom($xml)))->query('/domain/devices/disk/source/@file') ?: [] as $attr) {
        $paths[] = $attr->nodeValue;
    }
    return $paths;
}

// definition of a clone: new name, no UUID/MACs/fixed ports, disk paths replaced
// by $path_map [old path => new path]; UEFI variables get a fresh file from the template
function vm_xml_clone($xml, $new_name, array $path_map) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    vm_set_text($doc, 'name', $new_name);
    $remove = '/domain/uuid | /domain/devices/interface/mac | /domain/devices/interface/target'
        .' | /domain/devices/graphics/@port | /domain/devices/graphics/@websocket | /domain/os/nvram';
    foreach (iterator_to_array($xpath->query($remove)) as $node) {
        if ($node instanceof DOMAttr) {
            $node->ownerElement->removeAttributeNode($node);
        }
        else {
            $node->parentNode->removeChild($node);
        }
    }
    foreach ($xpath->query('/domain/devices/graphics') ?: [] as $graphics) {
        if ($graphics instanceof DOMElement) {
            $graphics->setAttribute('autoport', 'yes');
        }
    }
    foreach ($xpath->query('/domain/devices/disk/source[@file]') ?: [] as $source) {
        if ($source instanceof DOMElement && isset($path_map[$source->getAttribute('file')])) {
            $source->setAttribute('file', $path_map[$source->getAttribute('file')]);
        }
    }
    // a TCP text console port is unique per machine: the clone gets the default console
    if (vm_serial_port(vm_xml($doc)) > 0) {
        return vm_xml_set_serial(vm_xml($doc), 0);
    }
    return vm_xml($doc);
}

// volume name for a cloned disk: <new name>-<target>.<extension of the source>
function vm_clone_volume_name($new_name, $target, $source_path) {
    $ext = pathinfo($source_path, PATHINFO_EXTENSION);
    return $new_name.'-'.$target.($ext !== '' ? '.'.$ext : '');
}

// <memory> of the definition converted to MiB (libvirt default unit is KiB)
function vm_memory_mb($xml) {
    $memory = vm_first(vm_dom($xml), '/domain/memory');
    if (!$memory) {
        return 0;
    }
    $factors = ['b' => 1 / 1048576, 'bytes' => 1 / 1048576, 'k' => 1 / 1024, 'kib' => 1 / 1024, 'kb' => 1000 / 1048576,
        'm' => 1, 'mib' => 1, 'mb' => 1000000 / 1048576, 'g' => 1024, 'gib' => 1024, 'gb' => 1000000000 / 1048576];
    $unit = strtolower($memory->getAttribute('unit') ?: 'KiB');
    return (int)round((float)$memory->textContent * ($factors[$unit] ?? 1 / 1024));
}

// disks and CD-ROMs of a definition: [target => [device, bus, path, xml]]
function vm_disk_devices($xml) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    $list = [];
    foreach ($xpath->query('/domain/devices/disk') ?: [] as $disk) {
        $target = $xpath->query('target', $disk)->item(0);
        if (!$target instanceof DOMElement) {
            continue;
        }
        $source = $xpath->query('source/@file|source/@dev', $disk)->item(0);
        $list[$target->getAttribute('dev')] = [
            'device' => $disk instanceof DOMElement ? $disk->getAttribute('device') : '',
            'bus'    => $target->getAttribute('bus'),
            'path'   => $source ? $source->nodeValue : '',
            'xml'    => (string)$doc->saveXML($disk),
        ];
    }
    return $list;
}

// network interfaces: [mac => [type, source, model, xml]]
function vm_nic_devices($xml) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    $list = [];
    foreach ($xpath->query('/domain/devices/interface') ?: [] as $iface) {
        $mac = $xpath->query('mac/@address', $iface)->item(0);
        if (!$mac || !$iface instanceof DOMElement) {
            continue;
        }
        $source = $xpath->query('source/@network|source/@bridge|source/@dev', $iface)->item(0);
        $model = $xpath->query('model/@type', $iface)->item(0);
        $list[strtolower($mac->nodeValue)] = [
            'type'   => $iface->getAttribute('type'),
            'source' => $source ? $source->nodeValue : '',
            'model'  => $model ? $model->nodeValue : '',
            'xml'    => (string)$doc->saveXML($iface),
        ];
    }
    return $list;
}

// definition without the disk with this target
function vm_xml_remove_disk($xml, $target) {
    $doc = vm_dom($xml);
    foreach ((new DOMXPath($doc))->query('/domain/devices/disk[target/@dev='.vm_xpath_literal($target).']') ?: [] as $disk) {
        $disk->parentNode->removeChild($disk);
    }
    return vm_xml($doc);
}

// definition without the interface with this MAC (case insensitive)
function vm_xml_remove_nic($xml, $mac) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    foreach (iterator_to_array($xpath->query('/domain/devices/interface') ?: []) as $iface) {
        $address = $xpath->query('mac/@address', $iface)->item(0);
        if ($address && strtolower($address->nodeValue) === strtolower($mac)) {
            $iface->parentNode->removeChild($iface);
        }
    }
    return vm_xml($doc);
}

const NIC_MODELS = ['virtio' => 'virtio', 'e1000e' => 'Intel e1000e', 'e1000' => 'Intel e1000', 'rtl8139' => 'Realtek rtl8139'];

// interface XML connected to another libvirt network and/or with another model
function vm_nic_xml_change($nic_xml, $network, $model) {
    $doc = vm_dom($nic_xml);
    $iface = $doc->documentElement;
    $iface->setAttribute('type', 'network');
    foreach (iterator_to_array($iface->getElementsByTagName('source')) as $old) {
        $iface->removeChild($old);
    }
    $source = $doc->createElement('source');
    $source->setAttribute('network', $network);
    $iface->insertBefore($source, $iface->firstChild);
    $model_el = $iface->getElementsByTagName('model')->item(0);
    if (!$model_el) {
        $model_el = $doc->createElement('model');
        $iface->appendChild($model_el);
    }
    $old_model = $model_el->getAttribute('type');
    $model_el->setAttribute('type', $model);
    // another model may need another bus address; libvirt assigns a new one
    if ($old_model !== $model) {
        foreach (iterator_to_array($iface->getElementsByTagName('address')) as $address) {
            if ($address->parentNode === $iface) {
                $iface->removeChild($address);
            }
        }
    }
    return vm_xml($doc);
}

// definition with the interface (by MAC) replaced
function vm_xml_replace_nic($xml, $mac, $new_nic_xml) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    foreach (iterator_to_array($xpath->query('/domain/devices/interface') ?: []) as $iface) {
        $address = $xpath->query('mac/@address', $iface)->item(0);
        if ($address && strtolower($address->nodeValue) === strtolower($mac)) {
            $iface->parentNode->replaceChild($doc->importNode(vm_dom($new_nic_xml)->documentElement, true), $iface);
        }
    }
    return vm_xml($doc);
}

// XPath string literal for any value (quotes included)
function vm_xpath_literal($value) {
    $value = (string)$value;
    if (!str_contains($value, "'")) {
        return "'".$value."'";
    }
    if (!str_contains($value, '"')) {
        return '"'.$value.'"';
    }
    return "concat('".str_replace("'", "',\"'\",'", $value)."')";
}

// text console: $port > 0 = serial port on TCP 127.0.0.1:$port (reachable by websockify
// for the browser terminal), 0 = the default pty console (virsh console)
function vm_serial_xml($port) {
    $port = (int)$port;
    return $port > 0
        ? "<serial type='tcp'><source mode='bind' host='127.0.0.1' service='".$port."'/>"
            ."<protocol type='raw'/><target port='0'/></serial>"
        : "<serial type='pty'><target port='0'/></serial>";
}

// replaces serial ports and consoles of the definition with one text console (see vm_serial_xml)
function vm_xml_set_serial($xml, $port) {
    $doc = vm_dom($xml);
    foreach (iterator_to_array((new DOMXPath($doc))->query('/domain/devices/serial | /domain/devices/console') ?: []) as $node) {
        $node->parentNode->removeChild($node);
    }
    $devices = vm_first($doc, '/domain/devices');
    if ($devices) {
        $devices->appendChild(vm_element($doc, vm_serial_xml($port)));
    }
    return vm_xml($doc);
}

// TCP port of the text console (serial on 127.0.0.1), 0 when the machine has none
function vm_serial_port($xml) {
    $xpath = new DOMXPath(vm_dom($xml));
    $source = $xpath->query("/domain/devices/serial[@type='tcp']/source[@mode='bind']")->item(0);
    if (!$source instanceof DOMElement || !in_array($source->getAttribute('host'), ['127.0.0.1', 'localhost'], true)) {
        return 0;
    }
    return (int)$source->getAttribute('service');
}
