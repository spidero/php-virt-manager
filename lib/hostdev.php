<?php

// host device passthrough (USB, PCI): devices of the host from libvirt node
// devices, hostdev XML of a machine. A passed through device is taken from the
// host while the machine runs (managed='yes' detaches the host driver), so the
// lists leave out hubs and PCI bridges and show the current host driver.

// USB devices: key "usb:vvvv:pppp" (vendor:product, stays when replugged)
function hostdev_usb_list($con) {
    $list = [];
    foreach (@libvirt_list_nodedevs($con, 'usb_device') ?: [] as $name) {
        $dev = @libvirt_nodedev_get($con, $name);
        $xml = $dev ? @simplexml_load_string((string)libvirt_nodedev_get_xml_desc($dev)) : false;
        if (!$xml) {
            continue;
        }
        $cap = $xml->capability;
        $vendor = strtolower((string)$cap->vendor['id']);
        $product = strtolower((string)$cap->product['id']);
        $label = trim((string)$cap->vendor.' '.(string)$cap->product);
        // root hubs (Linux Foundation) and hubs cannot be passed through usefully
        if ($vendor === '0x1d6b' || preg_match('/\bhub\b/i', $label) || !preg_match('/^0x[0-9a-f]{4}$/', $vendor) || !preg_match('/^0x[0-9a-f]{4}$/', $product)) {
            continue;
        }
        $key = 'usb:'.substr($vendor, 2).':'.substr($product, 2);
        $list[$key] = ['key' => $key, 'type' => 'usb', 'label' => $label,
            'detail' => sprintf('%s:%s, bus %d device %d', substr($vendor, 2), substr($product, 2), (int)$cap->bus, (int)$cap->device)];
    }
    ksort($list);
    return $list;
}

// PCI devices except bridges: key "pci:dddd:bb:ss.f"
function hostdev_pci_list($con) {
    $list = [];
    foreach (@libvirt_list_nodedevs($con, 'pci') ?: [] as $name) {
        $dev = @libvirt_nodedev_get($con, $name);
        $xml = $dev ? @simplexml_load_string((string)libvirt_nodedev_get_xml_desc($dev)) : false;
        if (!$xml) {
            continue;
        }
        $cap = $xml->capability;
        $class = strtolower((string)$cap->class);
        if (str_starts_with($class, '0x06')) {
            continue;
        }
        $address = sprintf('%04x:%02x:%02x.%x', (int)$cap->domain, (int)$cap->bus, (int)$cap->slot, (int)$cap->function);
        $list['pci:'.$address] = ['key' => 'pci:'.$address, 'type' => 'pci',
            'label' => trim((string)$cap->vendor.' '.(string)$cap->product),
            'detail' => $address.', '.t('driver').': '.((string)$xml->driver->name ?: '-')
                .(isset($cap->iommuGroup) ? ', IOMMU '.(int)$cap->iommuGroup['number'] : '')];
    }
    ksort($list);
    return $list;
}

// hostdev XML for a key from the lists above; null when the key is invalid
function hostdev_xml($key) {
    if (preg_match('/^usb:([0-9a-f]{4}):([0-9a-f]{4})$/', (string)$key, $m)) {
        return "<hostdev mode='subsystem' type='usb' managed='yes'><source>"
            ."<vendor id='0x".$m[1]."'/><product id='0x".$m[2]."'/></source></hostdev>";
    }
    if (preg_match('/^pci:([0-9a-f]{4}):([0-9a-f]{2}):([0-9a-f]{2})\.([0-7])$/', (string)$key, $m)) {
        return "<hostdev mode='subsystem' type='pci' managed='yes'><source>"
            ."<address domain='0x".$m[1]."' bus='0x".$m[2]."' slot='0x".$m[3]."' function='0x".$m[4]."'/></source></hostdev>";
    }
    return null;
}

// host devices of a machine definition: [key => xml]
function vm_hostdevs($xml) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    $list = [];
    foreach ($xpath->query("/domain/devices/hostdev[@mode='subsystem']") ?: [] as $dev) {
        if (!$dev instanceof DOMElement) {
            continue;
        }
        $key = null;
        if ($dev->getAttribute('type') === 'usb') {
            $vendor = $xpath->query('source/vendor/@id', $dev)->item(0);
            $product = $xpath->query('source/product/@id', $dev)->item(0);
            if ($vendor && $product) {
                $key = 'usb:'.strtolower(substr($vendor->nodeValue, 2)).':'.strtolower(substr($product->nodeValue, 2));
            }
        }
        elseif ($dev->getAttribute('type') === 'pci') {
            $a = $xpath->query('source/address', $dev)->item(0);
            if ($a instanceof DOMElement) {
                $key = sprintf('pci:%04x:%02x:%02x.%x', hexdec($a->getAttribute('domain')), hexdec($a->getAttribute('bus')),
                    hexdec($a->getAttribute('slot')), hexdec($a->getAttribute('function')));
            }
        }
        if ($key !== null) {
            $list[$key] = (string)$doc->saveXML($dev);
        }
    }
    return $list;
}

// definition without the host device
function vm_xml_remove_hostdev($xml, $key) {
    $doc = vm_dom($xml);
    $xpath = new DOMXPath($doc);
    $target = vm_hostdevs($xml)[$key] ?? null;
    if ($target === null) {
        return $xml;
    }
    foreach (iterator_to_array($xpath->query('/domain/devices/hostdev') ?: []) as $dev) {
        if ((string)$doc->saveXML($dev) === $target) {
            $dev->parentNode->removeChild($dev);
            break;
        }
    }
    return vm_xml($doc);
}
