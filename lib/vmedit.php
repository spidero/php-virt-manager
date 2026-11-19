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

const VM_CPU_MODES = ['host-passthrough' => 'host-passthrough', 'host-model' => 'host-model'];

// memory and vCPU count of the persistent definition; $current_mb is the balloon
// target (default: all memory); $cpu = [mode, sockets, cores, threads] sets the CPU
// model and topology (sockets * cores * threads must equal $vcpus)
function vm_xml_set_resources($xml, $memory_mb, $vcpus, $current_mb = null, ?array $cpu = null) {
    $doc = vm_dom($xml);
    vm_set_text($doc, 'memory', (int)$memory_mb, ['unit' => 'MiB']);
    vm_set_text($doc, 'currentMemory', (int)($current_mb ?? $memory_mb), ['unit' => 'MiB']);
    vm_set_text($doc, 'vcpu', (int)$vcpus);
    if ($cpu !== null) {
        $cpu_el = vm_first($doc, '/domain/cpu');
        if (!$cpu_el) {
            $cpu_el = $doc->createElement('cpu');
            $doc->documentElement->appendChild($cpu_el);
        }
        // a named model (custom mode) does not fit another mode; the same mode keeps it
        $mode_changed = $cpu_el->getAttribute('mode') !== $cpu['mode'];
        foreach (iterator_to_array($cpu_el->childNodes) as $child) {
            if ($child instanceof DOMElement && ($child->nodeName === 'topology'
                    || ($mode_changed && in_array($child->nodeName, ['model', 'vendor'], true)))) {
                $cpu_el->removeChild($child);
            }
        }
        if ($mode_changed) {
            $cpu_el->setAttribute('mode', $cpu['mode']);
            // attributes of the old mode (migratable is only valid for host-passthrough/maximum)
            foreach (['match', 'check', 'migratable'] as $attr) {
                $cpu_el->removeAttribute($attr);
            }
        }
        $topology = $doc->createElement('topology');
        $topology->setAttribute('sockets', (string)(int)$cpu['sockets']);
        $topology->setAttribute('dies', '1');
        $topology->setAttribute('cores', (string)(int)$cpu['cores']);
        $topology->setAttribute('threads', (string)(int)$cpu['threads']);
        $cpu_el->insertBefore($topology, $cpu_el->firstChild);
    }
    return vm_xml($doc);
}

// CPU mode and topology of a definition (defaults: host-passthrough, one socket with all vCPUs)
function vm_cpu_settings($xml) {
    $doc = vm_dom($xml);
    $vcpus = max(1, (int)(vm_first($doc, '/domain/vcpu')->textContent ?? 1));
    $cpu = vm_first($doc, '/domain/cpu');
    $topology = vm_first($doc, '/domain/cpu/topology');
    return [
        'mode'    => $cpu && $cpu->getAttribute('mode') !== '' ? $cpu->getAttribute('mode') : 'host-passthrough',
        'sockets' => $topology ? max(1, (int)$topology->getAttribute('sockets')) : 1,
        'cores'   => $topology ? max(1, (int)$topology->getAttribute('cores')) : $vcpus,
        'threads' => $topology ? max(1, (int)$topology->getAttribute('threads')) : 1,
    ];
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

const DISK_BUSES = ['virtio' => 'virtio', 'scsi' => 'SCSI (virtio-scsi)', 'sata' => 'SATA'];
const DISK_CACHE_MODES = ['' => 'default', 'none' => 'none', 'writeback' => 'writeback', 'writethrough' => 'writethrough', 'directsync' => 'directsync', 'unsafe' => 'unsafe'];

// target name prefix of a bus (SATA and SCSI disks share the sdX names)
function vm_disk_target_prefix($bus) {
    return $bus === 'virtio' ? 'vd' : 'sd';
}

// disk device; $format qcow2 or raw (from the volume), $cache '' = libvirt default
function vm_disk_device_xml($path, $target, $bus = 'virtio', $format = 'qcow2', $cache = '') {
    $bus = isset(DISK_BUSES[$bus]) ? $bus : 'virtio';
    $format = in_array($format, ['qcow2', 'raw'], true) ? $format : 'raw';
    $cache_attr = $cache !== '' && isset(DISK_CACHE_MODES[$cache]) ? " cache='".$cache."'" : '';
    return "<disk type='file' device='disk'><driver name='qemu' type='".$format."' discard='unmap'".$cache_attr.'/>'
        ."<source file='".xml_escape($path)."'/><target dev='".xml_escape($target)."' bus='".$bus."'/></disk>";
}

// true when the definition has a virtio-scsi controller (needed by SCSI disks)
function vm_has_virtio_scsi($xml) {
    return vm_first(vm_dom($xml), "/domain/devices/controller[@type='scsi'][@model='virtio-scsi']") !== null;
}

function vm_cdrom_device_xml($iso_path, $target) {
    $source = $iso_path !== '' ? "<source file='".xml_escape($iso_path)."'/>" : '';
    return "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/>".$source
        ."<target dev='".xml_escape($target)."' bus='sata'/><readonly/></disk>";
}

// $target: libvirt network name or "network:/bridge:/direct:<name>" (see nic_target_parse())
function vm_nic_device_xml($target, $model = 'virtio') {
    [$type, $source] = nic_target_xml_parts($target);
    return "<interface type='".$type."'>".$source."<model type='".xml_escape($model)."'/></interface>";
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
// $skip: disk targets left out of the clone (shared disks keep their path: not in $path_map)
function vm_xml_clone($xml, $new_name, array $path_map, array $skip = []) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    foreach ($skip as $target) {
        foreach (iterator_to_array($xpath->query('/domain/devices/disk[target/@dev='.vm_xpath_literal($target).']') ?: []) as $disk) {
            $disk->parentNode->removeChild($disk);
        }
    }
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
// memory of the definition in MiB; $element 'currentMemory' for the balloon target
function vm_memory_mb($xml, $element = 'memory') {
    $memory = vm_first(vm_dom($xml), '/domain/'.$element) ?? ($element === 'currentMemory' ? vm_first(vm_dom($xml), '/domain/memory') : null);
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
            'target' => $iface->getAttribute('type').':'.($source ? $source->nodeValue : ''),
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

// interface XML connected elsewhere (libvirt network name or a target, see
// nic_target_parse()) and/or with another model
function vm_nic_xml_change($nic_xml, $target, $model) {
    $doc = vm_dom($nic_xml);
    $iface = $doc->documentElement;
    [$type, $source_xml] = nic_target_xml_parts($target);
    $iface->setAttribute('type', $type);
    foreach (iterator_to_array($iface->getElementsByTagName('source')) as $old) {
        $iface->removeChild($old);
    }
    $iface->insertBefore(vm_element($doc, $source_xml), $iface->firstChild);
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

// edited definition must describe the same machine: name and UUID unchanged
// (another name would define a second machine); returns error messages
function vm_xml_identity_errors($new_xml, $name, $uuid) {
    try {
        $doc = vm_dom($new_xml);
    }
    catch (InvalidArgumentException $e) {
        return [t('The XML is not well-formed.')];
    }
    if ($doc->documentElement->nodeName !== 'domain') {
        return [t('The root element must be <domain>.')];
    }
    $errors = [];
    $new_name = vm_first($doc, '/domain/name');
    if (!$new_name || trim($new_name->textContent) !== $name) {
        $errors[] = t('The name must stay %s (rename the machine instead).', $name);
    }
    $new_uuid = vm_first($doc, '/domain/uuid');
    if ($new_uuid && strtolower(trim($new_uuid->textContent)) !== strtolower($uuid)) {
        $errors[] = t('The UUID must not change.');
    }
    return $errors;
}

// schema check with virt-xml-validate (libvirt-clients); '' when valid or the tool
// is missing, otherwise its error output
function vm_xml_schema_error($xml) {
    if (!is_executable('/usr/bin/virt-xml-validate')) {
        return '';
    }
    $file = tempnam(sys_get_temp_dir(), 'pvm-xml-');
    file_put_contents($file, $xml);
    $proc = proc_open(['/usr/bin/virt-xml-validate', $file, 'domain'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = '';
    $status = 1;
    if (is_resource($proc)) {
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
    }
    @unlink($file);
    if ($status === 0) {
        return '';
    }
    // drop the temporary file name from the messages
    return trim(str_replace($file, 'XML', $output));
}

const CLONE_DISK_MODES = ['copy', 'share', 'skip'];

// disk modes for a clone from the request: target => copy|share|skip (default copy)
function vm_clone_disk_modes($xml, array $input) {
    $modes = [];
    foreach (array_keys(vm_disk_paths($xml)) as $target) {
        $mode = (string)($input[$target] ?? 'copy');
        $modes[$target] = in_array($mode, CLONE_DISK_MODES, true) ? $mode : 'copy';
    }
    return $modes;
}
