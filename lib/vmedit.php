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
